<?php

// This small utility imports the filezilla file into the database
//
// To use the generated data you need to set the following in the proftpd sql.conf file
//
//    SQLEngine               on
//    SQLPasswordEngine       on
//    SQLAuthenticate         on
//    SQLAuthTypes            SHA512
//    SQLPasswordEncoding     HEX
//    SQLPasswordUserSalt     sql:/get-user-salt Append
//    
//    SQLNamedQuery           get-user-salt SELECT "salt FROM users WHERE userid='%U'"
//
//
//
// Please note the utility uses the config.php in the ../config/ folder
//
//


global $cfg;

include_once (__DIR__ . "/../configs/config.php");
include_once (__DIR__ . "/../includes/AdminClass.php");


// SET the defaults here:
//
//
$filezillaBase = "E:\\FTP"; // will replace this with the value of $cfg['default_homedir']

$base[$cfg['field_gid']]=1001; // set it to the default group
$base[$cfg['field_disabled']]=0;
$base[$cfg['field_expiration']]='2099-12-31 00:00:00';
$base[$cfg['field_comment']]='Filezilla import';
$base[$cfg['field_uid']]="";
$base[$cfg['field_title']]="";
$base[$cfg['field_name']]="";
$base[$cfg['field_email']]="";
$base[$cfg['field_company']]="";
$base[$cfg['field_sshpubkey']]="";

//
// should not need to change anything here below
// 



//
// This is needed 'cause of php notice
if (!isset($cfg['read_login_defs']))
    $cfg['read_login_defs']=false;


if ($_SERVER['argc']==1) {
    echo "\nUSAGE: ".$_SERVER['argv'][0].'  <FileZilla.xml>'."\n\n";
    exit(2);
}

if (!file_exists($_SERVER['argv'][1])) {
    echo "\nERROR: file not found: ".$_SERVER['argv'][1]."\n\n";
    exit(3);
}
try {
    $xmlc = file_get_contents($_SERVER['argv'][1]);
    $xml = simplexml_load_string($xmlc);
} catch (Exception $e) {
    echo "\nERROR: something is wrong with the file\n\n";
    exit(4);
}

$ac = new AdminClass($cfg);




// sets default SHELL
//
if ($base[$cfg['field_shell']]=="")
    $base[$cfg['field_shell']]=$cfg['default_shell'];
else
    $base[$cfg['field_shell']]='/bin/false';

foreach ($xml->Users as $users) {
    foreach ($users as $userX) {
        $name = (string)$userX['Name'];
        $pwd = (string)$userX->Option[0];
        $salt = html_entity_decode($userX->Option[1],ENT_QUOTES|ENT_HTML5);
        $home = str_replace('\\','/',str_ireplace($filezillaBase,$cfg['default_homedir'],$userX->Permissions->Permission['Dir']));
        // Remove possible parameter in configuration
        $home = str_ireplace('/%U','',$home);

        if (empty($cfg['default_uid'])) {
            $uid    = $ac->get_last_uid() + 1;
        } else {
            $uid    = $cfg['default_uid'];
        }
        $user = $base;
        $user[$cfg['field_userid']]=$name;
        $user[$cfg['field_name']]=$name;
        $user[$cfg['field_uid']]=$uid;
        $user[$cfg['field_passwd']]=$pwd;
        $user[$cfg['field_salt']]=$salt;
        $user[$cfg['field_homedir']]=$home;
        /* now we add the user */
        try {
            if (!$ac->add_user($user)) {
                echo "ERROR: user not imported: ".$name."\n";
            } else {
                echo "USER importer: ".$name."\n";
            }
        } catch (Exception $e) {
            echo "ERROR: User cannot be added: ".$name."\n";
        }
    }
}

