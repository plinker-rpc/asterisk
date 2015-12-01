<?php
namespace plinker\Asterisk;

use RedBeanPHP\R;

class Asterisk {

    /**
     * Construct
     *
     * @param array $config
     */
    public function __construct(array $config = array(
        'database' => array(
            'dsn' => 'mysql:host=127.0.0.1;dbname=',
            'username' => '',
            'password' => '',
            'database' => '',
            'freeze' => false,
            'debug' => false,
        ),
        'ami' => array(
            'server' => '127.0.0.1',
            'port' => '5038',
            'username' => '',
            'password' => '',
        ),
    )) {
        $this->config = $config;

        //check database construct values
        if (empty($this->config['database'])) {
            exit(json_encode($this->response(
                'Bad Request',
                400,
                array('config construct error [database] empty')
            ), JSON_PRETTY_PRINT));
        } else {
            //hook in redbean
            new \plinker\Redbean\Redbean($this->config['database']);
        }

        //check ami construct values
        if (empty($this->config['ami'])) {
            exit(json_encode($this->response(
                'Bad Request',
                400,
                array('config construct error [ami] empty')
            ), JSON_PRETTY_PRINT));
        } else {
            //check ami connection
            if (!$this->_ASM_Connect()) {
                exit(json_encode($this->response(
                    'Unprocessable Entity',
                    422,
                    array('asterisk manager connection failed')
                ), JSON_PRETTY_PRINT));
            }
        }
    }

    /**
     * setter response, sets array structure and returns
     */
    private function response($data = null, $status = 200, $errors = array())
    {
        return array(
            'status' => $status,
            'errors' => $errors,
            'data'   => $data
        );
    }

    /**
     * Load ams manager class
     * @scope private
     */
    private function _ASM_Connect()
    {
        require_once(dirname(__FILE__).'/lib/phpagi-asmanager.php');

        $this->asm = new \AGI_AsteriskManager(null, array(
            'server'   => $this->config['ami']['server'],
            'port'     => $this->config['ami']['port'],
            'username' => $this->config['ami']['username'],
            'secret'   => $this->config['ami']['password'],
        ));

        if ($this->asm->connect()) {
            $this->asm->connected = true;
        } else {
            $this->asm->connected = false;
        }
        return $this->asm->connected;
    }

    /**
     * Local getter for the asm command method
     *
     * @param array $params
     */
    public function command($params = array())
    {
        return $this->asm->Command("{$params[0]}");
    }

    /**
     * Connect into AMI and issue asterisk command [queue show ?]
     *
     * @param array $parmams
     */
    public function getQueue($params = array())
    {
        return $this->asm->Command("queue show {$params[0]}");
    }

    /**
     * Connect into AMI and issue asterisk command [core show channels]
     *
     * @param array $params
     */
    public function coreShowChannels($params = array())
    {
        $result = $this->asm->Command("core show channels");
        $result = explode("\n", $result['data']);

        return array(
            'active_channels' => (int) $result[2],
            'active_calls' => (int) $result[3],
            'calls_processed' => (int) $result[4]
        );
    }

    /**
     * Connect into AMI and issue asterisk command [originate]
     *
     * @param array $params - contains $number, $ext, $context
     */
    public function dial($params = array())
    {
        $number = $params[0];
        $ext = $params[1];
        $context = $params[2];

        if (empty($number) || empty($ext)) {
            return 'number or extension not set';
        }

        if (empty($context)) {
            return 'context not set';
        }

        if ($this->asm->connected) {

            $call = $this->asm->Originate(
                'SIP/'.$ext,
                $number,
                $context,
                1
            );

            $this->asm->disconnect();
            return $call;
        } else {
            return 'premature connection lost to asterisk manager';
        }
    }

    /**
     * Connect into AMI and retrieve asterisk command [sip show peers] and
     * regex parse the response into an array
     *
     * @param array $params
     */
    public function sipShowPeers($params = array())
    {
        if ($this->asm->connected) {

            $peers = $this->asm->send_request(
                'Command',
                array('Command' => 'sip show peers')
            );

            $peers = explode("\n", $peers['data']);

            $result = array();
            foreach ($peers as $peer) {
                if (preg_match('/(.*[\/].*)\s+([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)\s+(\w+)\s+(\w+)\s+(\w+)\s+(\d+)\s+(.*[)])/i',$peer, $matches)) {
                    $row = array(
                        'ext' => $matches[1],
                        'ip' => $matches[2],
                        'dynamic' => $matches[3],
                        'forceport' => $matches[4],
                        'port' => $matches[6],
                        'state' => $matches[7],
                    );
                    $result[] = array_map('trim', $row);
                }
            }
            $this->asm->disconnect();

            return $result;
        } else {
            return 'premature connection lost to asterisk manager';
        }
    }

    // public function getLatestCalls($params = array())
    // {
    //     $count = $params[0];
    //     return $this->pdo->asterisk->raw_select('SELECT * FROM cdr ORDER BY calldate DESC LIMIT '.(int) $count);
    // }

    // public function getContacts($params = array())
    // {
    //     return $this->pdo->asterisk->raw_select(
    //         'SELECT *
    //          FROM cid
    //          ORDER BY id DESC
    //          LIMIT '.(int) (isset($params[0]) && is_numeric($params[0]) ? $params[0] : 10)
    //     );
    // }

    // public function getContact($params = array())
    // {
    //     return $this->pdo->asterisk->raw_select(
    //         'SELECT *
    //          FROM cid
    //          WHERE id = "'.(int) $params[0].'"
    //          ORDER BY id DESC
    //          LIMIT 1'
    //     );
    // }

    // public function newContact($params = array())
    // {
    //     return $this->pdo->asterisk->create('cid', array(array(
    //         'name'    => isset($params[0]) ? trim($params[0]) : 'Undefined',
    //         'number'  => isset($params[1]) ? trim($params[1]) : null,
    //         'added'   => time(),
    //         'updated' => time(),
    //     )));
    // }

    // public function updateContact($params = array())
    // {
    //     $this->pdo->asterisk->update('cid', 'name', $params[1], 'id', $params[0]);
    //     $this->pdo->asterisk->update('cid', 'number', $params[2], 'id', $params[0]);
    //     $this->pdo->asterisk->update('cid', 'updated', time(), 'id', $params[0]);
    //     return true;
    // }

    // public function deleteContact($params = array())
    // {
    //     return $this->pdo->asterisk->delete('cid', 'id', $params[0]);
    // }

}