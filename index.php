<?php
session_start();
// tested with official SDK  v.3.1.1 2011-12-10
require_once 'sdk/facebook.php';

$fb_app_id = '---- APP ID ----';
$fb_secret = '---- APP SECRET ----';
$fb_app_url = '---- APP URL ----';

$facebook = new Facebook(array(
	'appId'  => $fb_app_id,
	'secret' => $fb_secret
));

$user = $facebook->getUser();

if ($user) {
	// yeah, this part for the login stuff to work, nice eh?
	try {
		$user_profile = $facebook->api('/me');
	} catch (FacebookApiException $e) {
		error_log($e);
		$user = null;
	}
}

if ($user && !empty($_GET['code'])) {
	header('Location: ' . $fb_app_url);
}

if ($user) {
	$logoutUrl = $facebook->getLogoutUrl();
	$bday_list = array();
	try {
		$fql = 'SELECT uid, name, birthday_date, profile_url, third_party_id FROM user '
			. 'WHERE uid IN (SELECT uid2 FROM friend WHERE uid1 = me())';

		$bday_list = $facebook->api(array(
			'method' => 'fql.query',
			'query' => $fql,
		));
	} catch (FacebookApiException $e) {
		error_log($e);
	}
	$bdays = array();
	foreach ($bday_list as $fdata) {

		// no birthday set
		if (empty($fdata['birthday_date'])) {
			continue;
		}

		// b-day may be in format MM/DD or MM/DD/YYYY
		// but as we don't care about age, just store day and month
		$tmp = explode('/', $fdata['birthday_date']);
		if (count($tmp) < 2) {
			continue;
		}

		$bdays[] = array(
			'third_party_id' => (string)$fdata['third_party_id'],
			'name'           => $fdata['name'],
			'link'           => empty($fdata['profile_url']) ? '' : $fdata['profile_url'],
			'd'              => $tmp[1],
			'm'              => $tmp[0],
		);
	}

	// echo ICS calendar
	if (count($bdays) && !empty($_POST['continue'])) {
		$output = "BEGIN:VCALENDAR\n"
			. "PRODID:-//Mozilla.org/NONSGML Mozilla Calendar V1.1//EN\n"
			. "X-WR-CALNAME:Facebook friends' birthdays\n"
			. "VERSION:2.0\n";

		foreach ($bdays as $f) {
			$output .= "BEGIN:VEVENT\n";
			$output .= "CREATED:".gmdate('Ymd\THis')."\n";
			$output .= "DTSTAMP:" . date('Y') . sprintf('%02d%02d', $f['m'], $f['d']) . 'T120000' . "\n";
			$output .= "UID:fb-bday-{$f['third_party_id']}\n";
			$output .= "SUMMARY:" . $f['name'] . "'s Birthday\n";
			$output .= "CATEGORIES:Birthday\n";
			#$output .= "LOCATION:{$f['link']}\n";
			#$output .= "DESCRIPTION:{$f['link']}\n";
			$output .= sprintf("RRULE:FREQ=YEARLY;BYMONTHDAY=%d;BYMONTH=%d\n", $f['d'], $f['m']);
			$ds = strtotime(date('Y') . '-' . $f['m'] . '-' . $f['d']);
			$output .= "DTSTART;VALUE=DATE:" . date('Ymd', $ds) . "\n";
			$output .= "DTEND;VALUE=DATE:" . date('Ymd', strtotime('+1 day', $ds)) . "\n";
			$output .= "TRANSP:TRANSPARENT\n";
			$output .= "END:VEVENT\n";
		}
		$output .= "END:VCALENDAR";

		header('Content-Disposition: inline; filename="facebook.ics"');
		header('Content-type: text/calendar; charset=UTF-8');
		echo $output;
		exit;
	}
} else {
	$loginUrl = $facebook->getLoginUrl(array(
		'scope' => 'friends_birthday'
	));
}
?>
<?php if ($user): ?>
	<a href="<?php echo $logoutUrl; ?>">Logout</a>
	<br>
	<form method="post" action="index.php">
	<p>Hello <?php echo htmlspecialchars(empty($user_profile['name']) ? $user_profile['username'] : $user_profile['name']); ?></p>
	<p>You have <?php echo count($bdays); ?> friends who have marked their birthday.</p>
	<?php if (count($bdays)): ?>
		<input type="submit" value="Get ICS calendar file" name="continue" />
	<?php endif ?>
	</form>
<?php else: ?>
	<a href="<?php echo $loginUrl; ?>">Login</a>
<?php endif ?>
