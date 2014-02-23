<?php
# readable code probably written by Bryan, other code by Siebrand
# further tweaked by Ilmari Karonen
header("Content-Type: text/html; charset=utf-8");
mb_internal_encoding("UTF-8");

# FIXME: use localized namespace names from the Toolserver DB
$namespaces = array (
        -2 => 'Media',
        -1 => 'Special',
        0 => '',
        1 => 'Talk',
        2 => 'User',
        3 => 'User talk',
        4 => 'Project',
        5 => 'Project talk',
        6 => 'File',
        7 => 'File talk',
        8 => 'MediaWiki',
        9 => 'MediaWiki talk',
        10 => 'Template',
        11 => 'Template talk',
        12 => 'Help',
        13 => 'Help talk',
        14 => 'Category',
        15 => 'Category talk',
        100 => 'Portal',
        101 => 'Portal talk',
        );

$num = 100;
if (isset($_REQUEST['max'])) $num = intval($_REQUEST['max']);
if ($num > 10000) $num = 10000;

# normalize filename like MediaWiki's Title::SecureAndSplit() does
$imgname = '';
if (isset($_REQUEST['image'])) $imgname = $_REQUEST['image'];
$imgname = preg_replace( '/\xE2\x80[\x8E\x8F\xAA-\xAE]/S', '', $imgname );
$imgname = preg_replace( '/[ _\xA0\x{1680}\x{180E}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}]+/u', '_', $imgname );
$imgname = trim( $imgname, '_' );
$imgname = mb_strtoupper( mb_substr( $imgname, 0, 1 ) ) . mb_substr( $imgname, 1 );

# just to be safe, match filenames with both upper and lower case first letter
$image = '';
if ($imgname != '') $image = ' AND img IN ("'.
	mysql_escape_string($imgname).'", "'.
	mysql_escape_string(mb_strtolower( mb_substr( $imgname, 0, 1 ) ) . mb_substr( $imgname, 1 )).'") ';

$replacer = '';
if (isset($_REQUEST['replacer']) && $_REQUEST['replacer']) $replacer = 'NOT';

$status = '';
if (isset($_REQUEST['status']) && $_REQUEST['status']!='') $status = ' AND status = "'.
         mysql_escape_string($_REQUEST['status']).'" ';

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<meta http-equiv="Content-type" content="text/html;charset=UTF-8" />
	<title>CommonsDelinker recent <?php
if ($replacer == 'NOT')
        print "replacements";
else
        print "delinks";
?></title>
</head>
<body>
<div style="float:right"><a href="http://tools.wikimedia.de"><img style="vertical-align:top" border="0" src="http://tools.wikimedia.de/images/wikimedia-toolserver-button.png" alt="Powered by Wikimedia Toolserver"/></a></div><span style='border:2px solid red;display:inline;float:left;padding:2px;font-size:150%;background-color:white'><a target='_blank' href='http://donate.wikimedia.org/'>Donate to Wikimedia!</a></span><center style='width:100%; border-bottom:1px solid #AAAAAA;margin-bottom:3px;padding:2px;background-color:#AAFFAA'>This is a reporting interface for <a href="http://meta.wikimedia.org/wiki/User:CommonsDelinker">CommonsDelinker</a>.<br />&nbsp;<a href="https://jira.toolserver.org/browse/COMMONSDELINKER">Bug reports and feature requests</a></center>
<h1>CommonsDelinker recent <?php
if ($replacer == 'NOT')
        print "replacements";
else
        print "delinks";
#print "img='".$img."'";
?></h1>
<p>All parameters are optional. By default the last 100 delinks are shown.<br />
<small><a href="delinker.txt">delinker log</a>&nbsp;&mdash;&nbsp;<a href="helper.txt">helper log</a>&nbsp;&mdash;&nbsp;
<a href="?">delinks</a>&nbsp;&mdash;&nbsp;<a href="?replacer=1">replacements</a></small></p>
<form action="" method="get">
	<table>
		<tr>
			<td>&nbsp;</td>
			<td>File name</td>
			<td>Type</td>
 			<td>Show</td>
			<td>Replacements</td>
		</tr>
		<tr>
			<td><input type="submit" value="OK" /></td>
			<td><input type='text' size='30' name='image' id='image' value="<?php echo htmlspecialchars(strtr($imgname,'_',' ')) ?>"/></td>
			<td>
				<select name='status'>
					<option value='' <?php if ($_REQUEST['status'] == '') echo "selected='selected'"; ?>>All</option>
					<option value='ok' <?php if ($_REQUEST['status'] == 'ok') echo "selected='selected'"; ?>>Success</option>
					<option value='skipped' <?php if ($_REQUEST['status'] == 'skipped') echo "selected='selected'"; ?>>Skipped</option>
				</select>
			</td>
			<td>
				<select name='max'>
					<option value='50' <?php if ($num == 50) echo "selected='selected'"; ?>>50</option>
					<option value='100' <?php if ($num == 100) echo "selected='selected'"; ?>>100</option>
					<option value='250' <?php if ($num == 250) echo "selected='selected'"; ?>>250</option>
					<option value='500' <?php if ($num == 500) echo "selected='selected'"; ?>>500</option>
				</select>
			</td>
			<td>
				<input type='checkbox' name='replacer' value='1' <?php if ($replacer) echo "checked='checked'"; ?> />
			</td>
		</tr>
	</table>
</form>

<table border="1">
	<tr>
		<td>Timestamp</td>
		<td>File</td>
		<td>Wiki</td>
		<td>Page</td>
		<td>Result</td>
	</tr>
<?php

# used for tidying page URLs below, see wfUrlencode() in MediaWiki's GlobalFunctions.php
# the important bit is "%2F" -> "/", since leaving it out breaks links
$codes = array( '%3B', '%40', '%24', '%21', '%2A', '%28', '%29', '%2C', '%2F', '%3A' );
$chars = array( ';',   '@',   '$',   '!',   '*',   '(',   ')',   ',',   '/',   ':'   );

# @TODO: Don't hard code this, read $HOME/.my.cnf instead, FFS
$dl_pw = posix_getpwuid (posix_getuid ());
$rp_mycnf = parse_ini_file($dl_pw['dir'] . "/replica.my.cnf");
$db = mysql_connect('commonswiki.labsdb', $rp_mycnf['user'], $rp_mycnf['password'])
	or die('Could not connect!');
unset($rp_mycnf, $dl_pw);
mysql_select_db('p50380g51602_p_delinker_p');
$query = "SELECT timestamp, img, wiki, namespace, page_title, status
        FROM delinker WHERE newimg IS $replacer NULL $status
        $image ORDER BY timestamp DESC LIMIT $num";
$result = mysql_query($query) or die('Query failed!: '.$query);

$count = 0;
while ($row = mysql_fetch_assoc($result))
{
        $count++;
        $ts = $row['timestamp'];
        $ts = substr($ts, 0, 4).'-'.substr($ts, 4, 2).'-'.substr($ts, 6, 2).
                "&nbsp;".substr($ts, 8, 2).':'.substr($ts, 10, 2).':'.
                substr($ts, 12, 2);
        $ns = ( $row['namespace'] == 0 ? '' : $namespaces[$row['namespace']] . ':' );
        $title = $ns . $row['page_title'];
        $linktitle = str_ireplace( $codes, $chars, urlencode(strtr($title,' ','_')) );
        echo "\t<tr>\n";
        echo "\t\t<td>$ts</td>\n";
        echo "\t\t<td><a href=\"https://commons.wikimedia.org/wiki/Special:Log?page=";
        echo 'File:'.urlencode($row['img']).'">';
        echo htmlspecialchars(strtr($row['img'],'_',' '))."</a></td>\n";
        echo "\t\t<td><a href=\"http://";
        echo "{$row['wiki']}/wiki/Special:Contributions/CommonsDelinker\"";
        echo ">{$row['wiki']}</a></td>\n";
        echo "\t\t<td><a href=\"https://{$row['wiki']}/wiki/{$linktitle}\">";
        echo htmlspecialchars(strtr($title,'_',' '))."</a></td>\n";
        echo "\t\t<td>{$row['status']}</td>\n";
        echo "\t</tr>\n";

}
mysql_close($db);
?>
</table>
<?php
if ($imgname && $count == 0) {
	$imgname2 = preg_replace('/^(File|Image)_*:_*/i', '', $imgname);
	echo '<p>No ' . ($replacer ? 'replacements' : 'delinks');
	echo ' found for <i>' . htmlspecialchars(strtr($imgname,'_',' ')) . '</i>.';
	if ($imgname2 != $imgname) {
		echo ' Did you mean to search for <b><a href="';
		echo '?image=' . htmlspecialchars(urlencode($imgname2));
		echo '&amp;status=' . htmlspecialchars(urlencode($_REQUEST['status']));
		echo '&amp;max=' . $num . ($replacer ? '&amp;replacer=1' : '');
		echo '">' . htmlspecialchars(strtr($imgname2,'_',' '));
		echo '</a></b> instead?';
	}
	echo "</p>\n";
}
?>
		<p><a href="http://validator.w3.org/check?uri=referer"><img src="http://www.w3.org/Icons/valid-xhtml10" alt="Valid XHTML 1.0 Transitional" height="31" width="88" border="0" /></a></p>
	</body>
</html>
