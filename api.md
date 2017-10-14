## Table of contents

- [\Plinker\Asterisk\Asterisk](#class-plinkerasteriskasterisk)

<hr />

### Class: \Plinker\Asterisk\Asterisk

| Visibility | Function |
|:-----------|:---------|
| public | <strong>ExtensionState(</strong><em>array</em> <strong>$params=array()</strong>)</strong> : <em>void</em> |
| public | <strong>__construct(</strong><em>array</em> <strong>$config=array()</strong>)</strong> : <em>void</em><br /><em>Construct</em> |
| public | <strong>agiShowCommands(</strong><em>array</em> <strong>$params=array()</strong>)</strong> : <em>void</em> |
| public | <strong>callContact(</strong><em>array</em> <strong>$params=array()</strong>)</strong> : <em>void</em> |
| public | <strong>command(</strong><em>array</em> <strong>$params=array()</strong>)</strong> : <em>void</em><br /><em>Local getter for the asm command method</em> |
| public | <strong>coreShowChannels(</strong><em>array</em> <strong>$params=array()</strong>)</strong> : <em>void</em><br /><em>Connect into AMI and issue asterisk command [core show channels]</em> |
| public | <strong>create(</strong><em>array</em> <strong>$params=array()</strong>)</strong> : <em>mixed</em><br /><em>Create json $asterisk->create(string, array);</em> |
| public | <strong>deleteContact(</strong><em>array</em> <strong>$params=array()</strong>)</strong> : <em>void</em> |
| public | <strong>deleteWhere(</strong><em>array</em> <strong>$params=array()</strong>)</strong> : <em>void</em><br /><em>Delete bean by where query json $plink->delete(string, string);</em> |
| public | <strong>dial(</strong><em>array</em> <strong>$params=array()</strong>)</strong> : <em>void</em><br /><em>Connect into AMI and issue asterisk command [originate]</em> |
| public | <strong>exec(</strong><em>array</em> <strong>$params=array()</strong>)</strong> : <em>int</em><br /><em>Raw query json $plink->exec(string);</em> |
| public | <strong>findAll(</strong><em>array</em> <strong>$params=array()</strong>)</strong> : <em>mixed</em><br /><em>Find all json $plink->findAll(string, string, array);</em> |
| public | <strong>getActiveCalls(</strong><em>array</em> <strong>$params=array()</strong>)</strong> : <em>mixed</em> |
| public | <strong>getContact(</strong><em>array</em> <strong>$params=array()</strong>)</strong> : <em>mixed</em> |
| public | <strong>getContacts(</strong><em>array</em> <strong>$params=array()</strong>)</strong> : <em>mixed</em> |
| public | <strong>getLatestCalls(</strong><em>array</em> <strong>$params=array()</strong>)</strong> : <em>mixed</em> |
| public | <strong>getQueue(</strong><em>array</em> <strong>$params=array()</strong>)</strong> : <em>mixed</em><br /><em>Connect into AMI and issue asterisk command [queue show ?]</em> |
| public | <strong>getSysinfo(</strong><em>array</em> <strong>$params=array()</strong>)</strong> : <em>mixed</em> |
| public | <strong>newContact(</strong><em>array</em> <strong>$params=array()</strong>)</strong> : <em>void</em> |
| public | <strong>reload(</strong><em>array</em> <strong>$params=array()</strong>)</strong> : <em>void</em> |
| public | <strong>showChannels(</strong><em>array</em> <strong>$params=array()</strong>)</strong> : <em>void</em> |
| public | <strong>sipPeers(</strong><em>array</em> <strong>$params=array()</strong>)</strong> : <em>void</em> |
| public | <strong>sipShowPeers(</strong><em>array</em> <strong>$params=array()</strong>)</strong> : <em>void</em><br /><em>Connect into AMI and retrieve asterisk command [sip show peers] and regex parse the response into an array</em> |
| public | <strong>updateContact(</strong><em>array</em> <strong>$params=array()</strong>)</strong> : <em>void</em> |
| public | <strong>updateWhere(</strong><em>array</em> <strong>$params=array()</strong>)</strong> : <em>void</em><br /><em>Update bean by where query json $plink->updateWhere(string, string, array);</em> |

