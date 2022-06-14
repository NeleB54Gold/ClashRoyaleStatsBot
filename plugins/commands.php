<?php
	
# Ignore inline messages (via @)
if ($v->via_bot or $v->update['edited_message']) die;

# Player/Clan commands
if (in_array(explode(' ', $v->command, 2)[0], ['player', 'clan'])) {
	$v->text = explode(' ', $v->command, 2)[1];
	$user['settings']['select'] = explode(' ', $v->command, 2)[0] . 's';
	unset($v->command, $v->query_data);
}
# Player/Clan callbacks
elseif (in_array(explode(' ', $v->query_data, 2)[0], ['player', 'clan'])) {
	$v->text = explode(' ', $v->query_data, 2)[1];
	$user['settings']['select'] = explode(' ', $v->query_data, 2)[0] . 's';
	unset($v->command, $v->query_data);
}
# No other commands in groups and channnels
elseif (in_array($v->chat_type, ['group', 'supergroup', 'channels'])) {
	die;
}

# Private chat with Bot
if ($v->chat_type == 'private' or $v->inline_message_id) {
	if ($bot->configs['database']['status'] and $user['status'] !== 'started') $db->setStatus($v->user_id, 'started');
	# Set default selection
	if (!isset($user['settings']['select'])) $user['settings']['select'] = 'players';
	
	$watermark = 'Clash Royale Stats 👑' . PHP_EOL;
	# Change selection
	if (in_array($v->query_data, ['players', 'clans'])) {
		$user['settings']['select'] = $v->query_data;
		$db->query('UPDATE users SET settings = ? WHERE id = ?', [json_encode($user['settings']), $v->user_id]);
		$v->query_data = 'start';
	}
	# Edit message by inline messages
	if ($v->inline_message_id) {
		$v->message_id = $v->inline_message_id;
		$v->chat_id = 0;
	}
	# Test API
	if ($v->command == 'test' and $v->isAdmin()) {
		$cr = new ClashRoyale($db);
		$t = $bot->code(substr(json_encode($cr->getPlayer('#GQ09Q022'), JSON_PRETTY_PRINT), 0, 4096));
		if ($v->query_id) {
			$bot->editText($v->chat_id, $v->message_id, $t, $buttons);
			$bot->answerCBQ($v->query_id);
		} else {
			$bot->sendMessage($v->chat_id, $t, $buttons);
		}
	}
	# Start message
	elseif (in_array($v->command, ['start', 'start inline']) or $v->query_data == 'start') {
		$t = $bot->bold($watermark) . $bot->italic($tr->getTranslation('startMessage'), 1);
		$se = ['', ''];
		if ($user['settings']['select'] == 'clans') {
			$se[1] = '🔘';
		} else {
			$se[0] = '🔘';
		}
		$buttons[] = [
			$bot->createInlineButton($tr->getTranslation('playersButton') . $se[0], 'players'),
			$bot->createInlineButton($tr->getTranslation('clansButton') . $se[1], 'clans')
		];
		$buttons[] = [
			$bot->createInlineButton($tr->getTranslation('aboutButton'), 'about'),
			$bot->createInlineButton($tr->getTranslation('helpButton'), 'help')
		];
		$buttons[][] = $bot->createInlineButton($tr->getTranslation('changeLanguage'), 'lang');
		if ($v->query_id) {
			$bot->editText($v->chat_id, $v->message_id, $t, $buttons);
			$bot->answerCBQ($v->query_id);
		} else {
			$bot->sendMessage($v->chat_id, $t, $buttons);
		}
	}
	# Help message
	elseif ($v->command == 'help' or $v->query_data == 'help') {
		$buttons[][] = $bot->createInlineButton('◀️', 'start');
		$t = $bot->bold($watermark) . $tr->getTranslation('helpMessage');
		if ($v->query_id) {
			$bot->editText($v->chat_id, $v->message_id, $t, $buttons);
			$bot->answerCBQ($v->query_id);
		} else {
			$bot->sendMessage($v->chat_id, $t, $buttons);
		}
	}
	# About
	elseif ($v->command == 'about' or $v->query_data == 'about') {
		$buttons[][] = $bot->createInlineButton('◀️', 'start');
		$t = $tr->getTranslation('aboutMessage', [explode('-', phpversion(), 2)[0]]);
		if ($v->query_id) {
			$bot->editText($v->chat_id, $v->message_id, $t, $buttons);
			$bot->answerCBQ($v->query_id);
		} else {
			$bot->sendMessage($v->chat_id, $t, $buttons);
		}
	}
	# Change language
	elseif ($v->command == 'lang' or $v->query_data == 'lang' or strpos($v->query_data, 'changeLanguage-') === 0) {
		$langnames = [
			'en' => '🇬🇧 English',
			'es' => '🇪🇸 Español',
			'fr' => '🇫🇷 Français',
			'it' => '🇮🇹 Italiano'
		];
		if (strpos($v->query_data, 'changeLanguage-') === 0) {
			$select = str_replace('changeLanguage-', '', $v->query_data);
			if (in_array($select, array_keys($langnames))) {
				$tr->setLanguage($select);
				$user['lang'] = $select;
				$db->query('UPDATE users SET lang = ? WHERE id = ?', [$user['lang'], $user['id']]);
			}
		}
		$langnames[$user['lang']] .= ' ✅';
		$t = '🔡 Select your language';
		$formenu = 2;
		$mcount = 0;
		foreach ($langnames as $lang_code => $name) {
			if (isset($buttons[$mcount]) and count($buttons[$mcount]) >= $formenu) $mcount += 1;
			$buttons[$mcount][] = $bot->createInlineButton($name, 'changeLanguage-' . $lang_code);
		}
		$buttons[][] = $bot->createInlineButton('◀️', 'start');
		if ($v->query_id) {
			$bot->editText($v->chat_id, $v->message_id, $t, $buttons);
			$bot->answerCBQ($v->query_id);
		} else {
			$bot->sendMessage($v->chat_id, $t, $buttons);
		}
	}
	# Unknown command
	else {
		if (!$v->query_data and !$v->command and in_array($user['settings']['select'], ['players', 'clans'])) {
		} else {
			if ($v->command) {
				$t = $tr->getTranslation('unknownCommand');
			} elseif (!$v->query_id) {
				$t = $tr->getTranslation('noCommandRun');
			}
			if ($v->query_id) {
				$bot->answerCBQ($v->query_id, $t);
			} else {
				$bot->sendMessage($v->chat_id, $t);
			}
		}
	}
}

# General stats command
if (!$v->query_data and !$v->command and in_array($user['settings']['select'], ['players', 'clans'])) {
	$cr = new ClashRoyale($db);
	if ($user['settings']['select'] == 'clans') {
		$data = $cr->getclan($v->text);
		if ($data['tag']) {
			$args = [
				$data['name'],
				str_replace('#', '', $data['tag']),
				$data['description'],
				$data['members']
			];
			$buttons[][] = $bot->createInlineButton($tr->getTranslation('membersButton'), 'members ' . $data['tag']);
			if ($data['badgeId']) {
				$badgeUrl = 'https://cdn.statsroyale.com/images/badges/' . $data['badgeId'] . '.png';
			} else {
				$badgeUrl = 'https://cdn.statsroyale.com/images/badges/0.png';
			}
			$preview = $bot->text_link('&#8203;', $badgeUrl);
			$t = $preview . $tr->getTranslation('clanStats', $args);
		} else {
			$t = $tr->getTranslation('clanNotFound');
		}
	} else {
		$data = $cr->getPlayer($v->text);
		if ($data['tag']) {
			$args = [
				$data['name'],
				str_replace('#', '', $data['tag']),
				$data['expLevel'],
				$data['trophies'],
				$data['bestTrophies'],
				$data['wins'],
				$data['losses'],
				$data['battleCount'],
				$data['threeCrownWins'],
				$data['battleCount'] * 3,
				$data['challengeCardsWon'],
				$data['challengeMaxWins'],
				$data['tournamentCardsWon'],
				$data['tournamentBattleCount'],
				$data['donations'],
				$data['donationsReceived'],
				$data['totalDonations']
			];
			$buttons[][] = $bot->createInlineButton($tr->getTranslation('upcomingChestButton'), 'ucc ' . $data['tag']);
			if ($data['clan'] and $data['clan']['tag']) $buttons[][] = $bot->createInlineButton($tr->getTranslation('clansButton'), 'clan ' . $data['clan']['tag']);
			if ($data['clan'] and $data['clan']['badgeId']) {
				$badgeUrl = 'https://cdn.statsroyale.com/images/badges/' . $data['clan']['badgeId'] . '.png';
			} else {
				$badgeUrl = 'https://cdn.statsroyale.com/images/badges/0.png';
			}
			$preview = $bot->text_link('&#8203;', $badgeUrl);
			$t = $preview . $tr->getTranslation('playerStats', $args);
		} else {
			$t = $tr->getTranslation('playerNotFound');
		}
	}
	if ($v->query_id) {
		$bot->editText($v->chat_id, $v->message_id, $t, $buttons, 'def', 0);
		$bot->answerCBQ($v->query_id, $cbt);
	} else {
		$bot->sendMessage($v->chat_id, $t, $buttons, 'def', 0, 0);
	}
}
# Player chests command
if (strpos($v->query_data, 'ucc ') === 0) {
	$cr = new ClashRoyale($db);
	$player = $cr->getPlayer(str_replace('ucc ', '', $v->query_data));
	$data = $cr->getPlayerChests(str_replace('ucc ', '', $v->query_data));
	$preview = $bot->text_link('&#8203;', 'https://telegra.ph/file/be846016d86a27148e46b.jpg');
	$t = $preview . '🗝️ ' . $bot->bold($tr->getTranslation('upcomingChestsOf', [$player['name']]), 1) . PHP_EOL;
	foreach ($data['items'] as $num => $chest) {
		if ($num === 0) {
			$lemoji = '┌ ';
		} elseif ($num === (count($data['items']) - 1)) {
			$lemoji = '└ ';
		} else {
			$lemoji = '├ ';
		}
		$t .= PHP_EOL . $lemoji . ($chest['index'] + 1) . ') ' . $bot->code($chest['name'], 1);
	}
	$buttons[][] = $bot->createInlineButton('◀️', 'player ' . $player['tag']);
	$bot->editText($v->chat_id, $v->message_id, $t, $buttons, 'def', 0);
	$bot->answerCBQ($v->query_id);
}
# Member list comand
elseif (strpos($v->query_data, 'members ') === 0) {
	$cr = new ClashRoyale($db);
	$data = $cr->getclan(str_replace('members ', '', $v->query_data));
	$preview = $bot->text_link('&#8203;', 'https://telegra.ph/file/31fa521df68068c1bbef7.jpg');
	$t = $preview . $bot->bold($tr->getTranslation('membersListOf', [$data['name']]), 1) . PHP_EOL;
	foreach ($data['memberList'] as $num => $member) {
		if ($num === 0) {
			$lemoji = '┌';
		} elseif ($num === ($data['members'] - 1)) {
			$lemoji = '└';
		} else {
			$lemoji = '├';
		}
		$t .= PHP_EOL . $lemoji . ' [' . $bot->code($member['tag'], 1) . '] ' . $bot->specialchars($member['name'], 1);
	}
	$buttons[][] = $bot->createInlineButton('◀️', 'clan ' . $data['tag']);
	$bot->editText($v->chat_id, $v->message_id, $t, $buttons, 'def', 0);
	$bot->answerCBQ($v->query_id);
}

# Inline commands
if ($v->update['inline_query']) {
	$sw_text = 'Start the Bot!';
	$sw_arg = 'inline'; // The message the bot receive is '/start inline'
	$results = [];
	# Search players and clans with inline mode
	if ($v->query) {
		$cr = new ClashRoyale($db);
		if ($user['settings']['select'] == 'clans') {
			$data = $cr->getClan($v->query);
			if ($data['tag']) {
				$args = [
					$data['name'],
					str_replace('#', '', $data['tag']),
					$data['description'],
					$data['members']
				];
				$buttons[][] = $bot->createInlineButton($tr->getTranslation('membersButton'), 'members ' . $data['tag']);
				$t = $tr->getTranslation('clanStats', $args);
			} else {
				$t = $tr->getTranslation('clanNotFound');
			}
		} else {
			$data = $cr->getPlayer($v->query);
			if ($data['tag']) {
				$args = [
					$data['name'],
					str_replace('#', '', $data['tag']),
					$data['expLevel'],
					$data['trophies'],
					$data['bestTrophies'],
					$data['wins'],
					$data['losses'],
					$data['battleCount'],
					$data['threeCrownWins'],
					$data['battleCount'] * 3,
					$data['challengeCardsWon'],
					$data['challengeMaxWins'],
					$data['tournamentCardsWon'],
					$data['tournamentBattleCount'],
					$data['donations'],
					$data['donationsReceived'],
					$data['totalDonations']
				];
				$buttons[][] = $bot->createInlineButton($tr->getTranslation('upcomingChestButton'), 'ucc ' . $data['tag']);
				if ($data['clan'] and $data['clan']['badgeId']) {
					$badgeUrl = 'https://cdn.statsroyale.com/images/badges/' . $data['clan']['badgeId'] . '.png';
				} else {
					$badgeUrl = 'https://cdn.statsroyale.com/images/badges/0.png';
				}
				if ($data['clan'] and $data['clan']['tag']) $buttons[][] = $bot->createInlineButton($tr->getTranslation('clansButton'), 'clan ' . $data['clan']['tag']);
				$preview = $bot->text_link('&#8203;', $badgeUrl);
				$t = $preview . $tr->getTranslation('playerStats', $args);
			} else {
				$sw_text = $tr->getTranslation('playerNotFound');
			}
		}
		if ($t) {
			$results[] = $bot->createInlineArticle(
				$v->query,
				$data['name'],
				$data['tag'],
				$bot->createTextInput($t, 'def', 0),
				$buttons,
				0,
				0,
				$badgeUrl
			);
		}
	}
	$bot->answerIQ($v->id, $results, $sw_text, $sw_arg);
}

?>
