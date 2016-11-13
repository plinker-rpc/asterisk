<?php
namespace Plinker\Asterisk;

use RedBeanPHP\R;

class Asterisk {

    /**
     * Construct
     *
     * @param array $config - passed from the Plinker client
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

        // check database construct values
        if (empty($this->config['database'])) {
            exit(json_encode($this->response(
                'Bad Request',
                400,
                array('config construct error [database] empty')
            ), JSON_PRETTY_PRINT));
        } else {
            //hook in redbean
            new \Plinker\Redbean\Redbean($this->config['database']);
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
        require_once(dirname(__FILE__) . '/lib/phpagi-asmanager.php');

        if (empty($this->asm)) {
            $this->asm = new \AGI_AsteriskManager(null, array(
                'server'   => $this->config['ami']['server'],
                'port'     => $this->config['ami']['port'],
                'username' => $this->config['ami']['username'],
                'secret'   => $this->config['ami']['password'],
            ));
        }

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
                if (preg_match('/(.*[\/].*)\s+([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)\s+(\w+)\s+(\w+)\s+(\w+)\s+(\d+)\s+(.*[)])/i', $peer, $matches)) {
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

    /**
     *
     */
    public function agiShowCommands($params = array())
    {
        return $this->asm->Command("agi show commands");
    }

    /**
     *
     */
    public function showChannels($params = array())
    {
        return $this->asm->Command("core show channels");
    }

    /**
     *
     */
    public function getSysinfo($params = array())
    {
        return $this->asm->Command("core show sysinfo");
    }

    /**
     *
     */
    public function reload($params = array())
    {
        return $this->asm->Command("core reload");
    }

    /**
     *
     */
    public function ExtensionState($params = array())
    {
        return $this->asm->ExtensionState(@$params[0], @$params[1], @$params[1]);
    }

    /**
     *
     */
    public function getActiveCalls($params = array())
    {
        return trim(shell_exec('asterisk -rx "core show channels" | grep "active call"'));
    }

    /**
     * Create
     *
     * json $asterisk->create(string, array);
     *
     * @param array $params
     */
    public function create(array $params = array())
    {
        $result = R::dispense($params[0]);
        $result->import($params[1]);
        R::store($result);

        return R::exportAll($result);
    }

    /**
     * Update bean by where query
     * json $plink->updateWhere(string, string, array);
     *
     * @param array $params
     */
    public function updateWhere(array $params = array())
    {
        $result = R::findOne($params[0], $params[1]);

        if (!empty($result)) {
            $result->import($params[2]);
            R::store($result);
            return R::exportAll($result);
        }

        return [];
    }

    /**
     * Find all
     *
     * json $plink->findAll(string, string, array);
     *
     * @link http://www.redbeanphp.com/index.php?p=/finding#find_all
     * @param array $params
     */
    public function findAll(array $params = array())
    {
        if (!empty($params[1]) && !empty($params[2])) {
            $result = R::findAll($params[0], $params[1], $params[2]);
        } elseif (!empty($params[1]) && empty($params[2])) {
            $result = R::findAll($params[0], $params[1]);
        } else {
            $result = R::findAll($params[0]);
        }

        return R::exportAll($result);
    }

    /**
     * Delete bean by where query
     *
     * json $plink->delete(string, string);
     *
     * @param array $params
     */
    public function deleteWhere(array $params = array())
    {
        $result = R::findOne($params[0], $params[1]);
        return R::trash($result);
    }

    /**
     * Raw query
     *
     * json $plink->exec(string);
     *
     * @param array $params
     * @return int
     */
    public function exec(array $params = array())
    {
        return R::exec($params[0]);
    }

    /**
     *
     */
    public function getLatestCalls($params = array())
    {
        $count = $params[0];
        return $this->pdo->asterisk->raw_select('SELECT * FROM cdr ORDER BY calldate DESC LIMIT '.(int) $count);
    }

    /**
     *
     */
    public function getContacts($params = array())
    {
        return $this->pdo->asterisk->raw_select(
            'SELECT *
             FROM cid
             ORDER BY id DESC
             LIMIT '.(int) (isset($params[0]) && is_numeric($params[0]) ? $params[0] : 10)
        );
    }

    /**
     *
     */
    public function getContact($params = array())
    {
        return $this->pdo->asterisk->raw_select(
            'SELECT *
             FROM cid
             WHERE id = "'.(int) $params[0].'"
             ORDER BY id DESC
             LIMIT 1'
        );
    }

    /**
     *
     */
    public function newContact($params = array())
    {
        return $this->pdo->asterisk->create('cid', array(array(
            'name'    => isset($params[0]) ? trim($params[0]) : 'Undefined',
            'number'  => isset($params[1]) ? trim($params[1]) : null,
            'added'   => time(),
            'updated' => time(),
        )));
    }

    /**
     *
     */
    public function updateContact($params = array())
    {
        $this->pdo->asterisk->update('cid', 'name', $params[1], 'id', $params[0]);
        $this->pdo->asterisk->update('cid', 'number', $params[2], 'id', $params[0]);
        $this->pdo->asterisk->update('cid', 'updated', time(), 'id', $params[0]);
        return true;
    }

    /**
     *
     */
    public function deleteContact($params = array())
    {
        return $this->pdo->asterisk->delete('cid', 'id', $params[0]);
    }

    /**
     *
     */
    public function callContact($params = array())
    {
        $number = $params[0];
        $ext = $params[1];

        if (empty($number) || empty($ext)) {
            return 'number or extension not set';
        }

        $valid = $this->pdo->asterisk->raw_select(
            'SELECT 1
             FROM cid
             WHERE number = "'.$number.'"'
        );

        if (count($valid) != 1) {
            return 'invalid contact, only numbers in the contact book can be dialed';
        }

        if ($this->connected) {

            $call = $this->asm->Originate(
                'SIP/'.$ext,
                $number,
                'outbound',
                1
            );

            $this->asm->disconnect();
            return $call;
        } else {
            return 'premature connection lost to asterisk manager';
        }
    }

    /**
     *
     */
    public function sipPeers($params = array())
    {
        if ($this->connected) {

            $peers = $this->asm->send_request(
                'Command',
                array('Command' => 'sip show peers')
            );
            $peers = explode("\n", $peers['data']);

            $result = array();
            foreach ($peers as $peer) {
                if(preg_match('/(.*[\/].*)\s+([0-9]+.[0-9]+.[0-9]+.[0-9]+)\s+(\w)\s+(\w)\s+(\d+)\s+(\w+\s.*)/i',$peer, $matches)){
                    $row = array(
                        'ext' => $matches[1],
                        'ip' => $matches[2],
                        'dynamic' => $matches[3],
                        'forceport' => $matches[4],
                        'port' => $matches[5],
                        'state' => $matches[6],
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

    /*
                             ! Execute a shell command
                      acl show Show a named ACL or list all named ACLs
                    ael reload Reload AEL configuration
ael set debug {read|tokens|mac Enable AEL debugging flags
                 agi dump html Dumps a list of AGI commands in HTML format
                      agi exec Add AGI command to a channel in Async AGI
        agi set debug [on|off] Enable/Disable AGI debugging
     agi show commands [topic] List AGI commands or specific help
                 aoc set debug enable cli debugging of AOC messages
                     cc cancel Kill a CC transaction
              cc report status Reports CC stats
              cdr mysql status Show connection status of cdr_mysql
               cdr show status Display the CDR status
               cel show status Display the CEL status
             channel originate Originate a call
              channel redirect Redirect a call
        channel request hangup Request a hangup on a given channel
         cli check permissions Try a permissions config for a user
        cli reload permissions Reload CLI permissions config
              cli show aliases Show CLI command aliases
          cli show permissions Show CLI permissions
               confbridge kick Kick participants out of conference bridges.
               confbridge list List conference bridges and participants.
               confbridge lock Lock a conference.
               confbridge mute Mute a participant.
       confbridge record start Start recording a conference
        confbridge record stop Stop recording a conference.
          confbridge show menu Show a conference menu
         confbridge show menus Show a list of conference menus
confbridge show profile bridge Show a conference bridge profile.
confbridge show profile bridge Show a list of conference bridge profiles.
  confbridge show profile user Show a conference user profile.
 confbridge show profile users Show a list of conference user profiles.
             confbridge unlock Unlock a conference.
             confbridge unmute Unmute a participant.
                   config list Show all files that have loaded a configuration file
                 config reload Force a reload on modules using a particular configuration file
           core abort shutdown Cancel a running shutdown
            core clear profile Clear profiling info
       core ping taskprocessor Ping a named task processor
                   core reload Global reload
       core restart gracefully Restart Asterisk gracefully
              core restart now Restart Asterisk immediately
  core restart when convenient Restart Asterisk at empty call volume
        core set debug channel Enable/disable debugging on a channel
      core set {debug|verbose} Set level of debug/verbose chattiness
core show applications [like|d Shows registered dialplan applications
         core show application Describe a specific dialplan application
      core show calls [uptime] Display information on calls
core show channels [concise|ve Display information on channels
             core show channel Display information on a specific channel
        core show channeltypes List available channel types
         core show channeltype Give more details on that channel type
core show codecs [audio|video| Displays a list of codecs
               core show codec Shows a specific codec
     core show config mappings Display config mappings (file names to config engines)
        core show file formats Displays file formats
 core show file version [like] List versions of files used to build Asterisk
    core show functions [like] Shows registered dialplan functions
            core show function Describe a specific dialplan function
  core show hanguphandlers all Show hangup handlers of all channels
      core show hanguphandlers Show hangup handlers of a specified channel
                core show help Display help list, or specific help on a command
               core show hints Show dialplan hints
                core show hint Show dialplan hint
       core show image formats Displays image formats
             core show license Show the license(s) for this copy of Asterisk
             core show profile Display profiling info
            core show settings Show some core settings
            core show switches Show alternative switches
             core show sysinfo Show System Information
      core show taskprocessors List instantiated task processors and statistics
             core show threads Show running threads
         core show translation Display translation matrix
    core show uptime [seconds] Show uptime information
             core show version Display version info
            core show warranty Show the warranty (if any) for this copy of Asterisk
          core stop gracefully Gracefully shut down Asterisk
                 core stop now Shut down Asterisk immediately
     core stop when convenient Shut down Asterisk at empty call volume
          core waitfullybooted Wait for Asterisk to be fully booted
         dahdi destroy channel Destroy a channel
                 dahdi restart Fully restart DAHDI channels
                 dahdi set dnd Sets/resets DND (Do Not Disturb) mode on a channel
              dahdi set hwgain Set hardware gain on a channel
              dahdi set swgain Set software gain on a channel
           dahdi show cadences List cadences
dahdi show channels [group|con Show active DAHDI channels
            dahdi show channel Show information on a channel
             dahdi show status Show all DAHDI cards status
            dahdi show version Show the DAHDI version in use
                      data get Data API get
           data show providers Show data providers
                  database del Removes database key/value
              database deltree Removes database keytree/values
                  database get Gets database value
                  database put Adds/updates database value
                database query Run a user-specified query on the astdb
                 database show Shows database contents
              database showkey Shows database contents
               devstate change Change a custom device state
                 devstate list List currently known custom device states
        dialplan add extension Add new extension into context
        dialplan add ignorepat Add new ignore pattern
          dialplan add include Include context in other context
                dialplan debug Show fast extension pattern matching data structures
               dialplan reload Reload extensions and *only* extensions
       dialplan remove context Remove a specified context
     dialplan remove extension Remove a specified extension
     dialplan remove ignorepat Remove ignore pattern from context
       dialplan remove include Remove a specified include from context
                 dialplan save Save current dialplan into a file
          dialplan set chanvar Set a channel variable
dialplan set extenpatternmatch Use the Old extension pattern matching algorithm.
dialplan set extenpatternmatch Use the New extension pattern matching algorithm.
           dialplan set global Set global dialplan variable
         dialplan show chanvar Show channel variables
         dialplan show globals Show global dialplan variables
                 dialplan show Show dialplan
                dnsmgr refresh Performs an immediate refresh
                 dnsmgr reload Reloads the DNS manager configuration
                 dnsmgr status Display the DNS manager status
              event dump cache Dump the internal event cache (for debugging)
        fax set debug {on|off} Enable/Disable FAX debugging on new FAX sessions
         fax show capabilities Show the capabilities of the registered FAX technology modules
              fax show session Show the status of the named FAX sessions
             fax show sessions Show the current FAX sessions
             fax show settings Show the global settings and defaults of both the FAX core and technology modules
                fax show stats Summarize FAX session history
              fax show version Show versions of FAX For Asterisk components
               features reload Reloads configured features
                 features show Lists configured features
                  file convert Convert audio file
           group show channels Display active channels with group(s)
              http show status Display HTTP server status
                iax2 provision Provision an IAX device
           iax2 prune realtime Prune a cached realtime lookup
                   iax2 reload Reload IAX configuration
  iax2 set debug {on|off|peer} Enable/Disable IAX debugging
    iax2 set debug jb {on|off} Enable/Disable IAX jitterbuffer debugging
 iax2 set debug trunk {on|off} Enable/Disable IAX trunk debugging
                  iax2 set mtu Set the IAX systemwide trunking MTU
               iax2 show cache Display IAX cached dialplan
    iax2 show callnumber usage Show current entries in IP call number limit table
            iax2 show channels List active IAX channels
            iax2 show firmware List available IAX firmware
            iax2 show netstats List active IAX channel netstats
                iax2 show peer Show details on specific IAX peer
               iax2 show peers List defined IAX peers
        iax2 show provisioning Display iax provisioning
            iax2 show registry Display IAX registration status
               iax2 show stats Display IAX statistics
             iax2 show threads Display IAX helper thread info
        iax2 show users [like] List defined IAX users
             iax2 test losspct Set IAX2 incoming frame loss percentage
               iax2 unregister Unregister (force expiration) an IAX2 peer from the registry
                indication add Add the given indication to the country
             indication remove Remove the given indication from the country
               indication show Display a list of all countries/indications
                     keys init Initialize RSA key passcodes
                     keys show Displays RSA key information
           local show channels List status of local channels
                   logger mute Toggle logging output to a console
                 logger reload Reopens the log files
                 logger rotate Rotates and reopens the log files
logger set level {DEBUG|NOTICE Enables/Disables a specific logging level for this console
          logger show channels List configured log channels
                manager reload Reload manager configurations
    manager set debug [on|off] Show, enable, disable debugging of the manager code
          manager show command Show a manager interface command
         manager show commands List manager interface commands
        manager show connected List connected manager interface users
           manager show eventq List manager interface queued events
           manager show events List manager interface events
            manager show event Show a manager interface event
         manager show settings Show manager global settings
            manager show users List configured manager users
             manager show user Display information on a specific manager user
                   meetme kick Kick a conference or a user in a conference.
                   meetme list List all conferences or a specific conference.
          meetme {lock|unlock} Lock or unlock a conference to new users.
          meetme {mute|unmute} Mute or unmute a conference or a user in a conference.
     mfcr2 call files [on|off] Enable/Disable MFC/R2 call files
             mfcr2 set blocked Reset MFC/R2 channel forcing it to BLOCKED
               mfcr2 set debug Set MFC/R2 channel logging level
                mfcr2 set idle Reset MFC/R2 channel forcing it to IDLE
mfcr2 show channels [group|con Show MFC/R2 channels
           mfcr2 show variants Show supported MFC/R2 variants
            mfcr2 show version Show OpenR2 library version
           mgcp audit endpoint Audit specified MGCP endpoint
                   mgcp reload Reload MGCP configuration
       mgcp set debug {on|off} Enable/Disable MGCP debugging
           mgcp show endpoints List defined MGCP endpoints
          minivm list accounts List defined mini-voicemail boxes
         minivm list templates List message templates
             minivm list zones List zone message formats
                 minivm reload Reload Mini-voicemail configuration
          minivm show settings Show mini-voicemail general settings
             minivm show stats Show some mini-voicemail statistics
  mixmonitor {start|stop|list} Execute a MixMonitor command
                   module load Load a module by name
                 module reload Reload configuration for a module
            module show [like] List modules and info
                 module unload Unload a module by name
                    moh reload Reload MusicOnHold
              moh show classes List MusicOnHold classes
                moh show files List MusicOnHold file-based classes
              no debug channel Disable debugging on channel(s)
                     odbc show List ODBC DSN(s)
              parkedcalls show List currently parked calls
         phoneprov show routes Show registered phoneprov http routes
          presencestate change Change a custom presence state
            presencestate list List currently know custom presence states
   pri service disable channel Remove a channel from service
    pri service enable channel Return a channel to service
pri set debug {on|off|hex|inte Enables PRI debugging on a span
            pri set debug file Sends PRI debug output to the specified file
             pri show channels Displays PRI channel information
                pri show debug Displays current PRI debug settings
                pri show spans Displays PRI span information
                 pri show span Displays PRI span information
              pri show version Displays libpri version
              queue add member Add a channel to a specified queue
queue reload {parameters|membe Reload queues, members, queue rules, or parameters
           queue remove member Removes a channel from a specified queue
             queue reset stats Reset statistics for a queue
             queue set penalty Set penalty for a channel of a specified queue
           queue set ringinuse Set ringinuse for a channel of a specified queue
                    queue show Show status of a specified queue
              queue show rules Show the rules defined in queuerules.conf
  queue {pause|unpause} member Pause or unpause a queue member
              realtime destroy Delete a row from a RealTime database
                 realtime load Used to print out RealTime variables.
          realtime mysql cache Shows cached tables within the MySQL realtime driver
         realtime mysql status Shows connection information for the MySQL RealTime driver
                realtime store Store a new row into a RealTime database
               realtime update Used to update RealTime variables.
              realtime update2 Used to test the RealTime update2 method
    rtcp set debug {on|off|ip} Enable/Disable RTCP debugging
       rtcp set stats {on|off} Enable/Disable RTCP stats
     rtp set debug {on|off|ip} Enable/Disable RTP debugging
            say load [new|old] Set or show the say mode
                    sip notify Send a notify packet to a SIP peer
 sip prune realtime [peer|all] Prune cached Realtime users/peers
              sip qualify peer Send an OPTIONS packet to a peer
                    sip reload Reload SIP configuration
sip set debug {on|off|ip|peer} Enable/Disable SIP debugging
      sip set history {on|off} Enable/Disable SIP history
sip show {channels|subscriptio List active SIP channels or subscriptions
         sip show channelstats List statistics for active SIP channels
              sip show channel Show detailed SIP channel info
              sip show domains List our local SIP domains
              sip show history Show SIP dialog history
                sip show inuse List all inuse/limits
                  sip show mwi Show MWI subscriptions
              sip show objects List all SIP object allocations
                sip show peers List defined SIP peers
                 sip show peer Show details on specific SIP peer
             sip show registry List SIP registration status
                sip show sched Present a report on the status of the scheduler queue
             sip show settings Show SIP global settings
                  sip show tcp List TCP Connections
                sip show users List defined SIP users
                 sip show user Show details on specific SIP user
                sip unregister Unregister (force expiration) a SIP peer from the registry
             sla show stations Show SLA Stations
               sla show trunks Show SLA Trunks
                 ss7 block cic Blocks the given CIC
             ss7 block linkset Blocks all CICs on a linkset
ss7 set debug {on|off} linkset Enables SS7 debugging on a linkset
             ss7 show channels Displays SS7 channel information
              ss7 show linkset Shows the status of a linkset
              ss7 show version Displays libss7 version
               ss7 unblock cic Unblocks the given CIC
           ss7 unblock linkset Unblocks all CICs on a linkset
       stun set debug {on|off} Enable/Disable STUN debugging
                   timing test Run a timing test
               transcoder show Display DAHDI transcoder utilization.
   udptl set debug {on|off|ip} Enable/Disable UDPTL debugging
             udptl show config Show UDPTL config options
                        ulimit Set or show process resource limits
              voicemail reload Reload voicemail configuration
          voicemail show users List defined voicemail boxes
          voicemail show zones List zone message formats
                     wat debug Enables WAT debugging
                      wat exec Executes an arbitrary AT command
                  wat send sms Sends a SMS
                wat show spans Displays WAT span information
                 wat show span Displays WAT span information
              wat show version Displays libwat version
    */

}