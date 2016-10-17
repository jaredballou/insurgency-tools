<?php
//Root Path Discovery
if (!isset($rootpath)) { do { $rd = (isset($rd)) ? dirname($rd) : realpath(dirname(__FILE__)); $tp="{$rd}/rootpath.php"; if (file_exists($tp)) { require_once($tp); break; }} while ($rd != '/'); }
/*
	CONFIG
	This should ONLY be declaring default values or doing minimal work to set them.
	Code should go in functions.php when possible
	Remember: This file will be updated by Git, so any site-specific settings
	like passwords or anything private will be overwritten. Use config.private.php
	to set your own variables.
*/

// Servers, in the format 'server.domain.com:27015' - even if it's on the standard port
$servers = array();

// URL base for referencing public items
$urlbase = '/';

// Page Names
$curpage = basename($_SERVER['SCRIPT_FILENAME']);
$pages = array(
	"stats.php" => 'Stats',
	"maps.php" => 'Maps',
	"cvarlist.php" => 'CVARs',
	"theater.php" => 'Theater Creator',
	"https://trello.com/b/4W2CNdnL/very-not-fun-servers' target='_blank" => 'Roadmap',
	"https://github.com/jaredballou' target='_blank" => 'My Github',
	"https://www.nfoservers.com/donate.pl?force_recipient=1&recipient=nfoservers@jballou.com' target='_blank" => 'Donate'
);

// GitHub secret for post-commit hooks
$github_secret='';

//User to pull the GitHub readme files
$githubuser = 'jaredballou';

// Insurgency App ID
$appid = 222880;

// Steam API Key (PUT IN config.private.php !!!!)
$apikey = '';

$servers = array(
	'your.server.com:27015' => array(
		'rcon_password' => 'yourpassword',
	)
);
	
// publicpath is the publicly viewable path
$publicpath="${rootpath}/public";
// datapath is where the insurgency-data repo is checked out
$datapath="${publicpath}/data";

$theater_object_fields=array(
	'theater/ammo',
	'theater/explosives',
	'theater/player_gear',
	'theater/player_templates',
	'theater/teams',
	'theater/teams/*/squads',
	'theater/weapons',
	'theater/weapon_upgrades',
	'theater/weapon_upgrades/*/world_attachments',
	'theater/weapon_upgrades/*/viewmodel_attachments/*/weapons',
);

$ordered_fields = array(
	'theater/core/precache',
	'theater/teams/*/squads/*',
	'theater/player_templates/*/buy_order',
	'theater/player_templates/*/allowed_items',
	'theater/player_templates/*/allowed_weapons',
	'theater/weapon_upgrades/*/allowed_weapons',
	'theater/weapon_upgrades/*/viewmodel_attachments/*/weapons',
	'theater/weapon_upgrades/*/world_attachments/*/weapons',
	'theater/weapon_upgrades/*/viewmodel_attachments/*/excluded_weapons',
	'theater/weapon_upgrades/*/world_attachments/*/excluded_weapons',
);

$allow_duplicates_fields = array(
	'theater/teams/*/squads/*',
	'theater/weapon_upgrades/*/viewmodel_attachments',
	'theater/weapon_upgrades/*/world_attachments',
);
// Set language
$language = "English";

// Library include paths
$libpaths = explode(PATH_SEPARATOR,get_include_path());

// Custom libraries to load
$custom_libpaths = array(
	"{$rootpath}/thirdparty/php-binary",
	"{$rootpath}/thirdparty/php-binary/src",
	"{$rootpath}/thirdparty/php-binary/src/Exception",
	"{$rootpath}/thirdparty/php-binary/src/Field",
	"{$rootpath}/thirdparty/php-binary/src/Stream",
	"{$rootpath}/thirdparty/php-binary/src/Validator",
	"{$rootpath}/thirdparty/php-binary",
	"{$rootpath}/thirdparty/steam-condenser-php",
	"{$rootpath}/thirdparty/steam-condenser-php/vendor",
	"{$rootpath}/thirdparty/steam-condenser-php/lib",
	"{$rootpath}/thirdparty/steam-condenser-php/lib/SteamCondenser"
);

// For theater conditions
$theater_conditions=array();

// Base 

//theater path
$theaterpath='';

// Custom theater paths - include insurgency-theaters checkout
$custom_theater_paths = array('Custom' => "${rootpath}/theaters");

// MySQL Server connection settings
$mysql_server   = 'localhost';
$mysql_username = 'username';
$mysql_password = 'password';
$mysql_database = 'database';

// HLStatsX Variables
// Database prefix
$dbprefix = isset($_REQUEST['dbprefix']) ? $_REQUEST['dbprefix'] : 'hlstats';
// Game code (name of game in database tables)
$gamecode = isset($_REQUEST['gamecode']) ? $_REQUEST['gamecode'] : 'insurgency';
// Root of HLStatsX installation
$hlstatsx_root='/opt/hlstatsx-community-edition';
// Location of heat map images (need to be manually generated)
$hlstatsx_heatmaps="{$hlstatsx_root}/web/hlstatsimg/games/{$gamecode}/heatmaps";
// Location of HLStatsX Config file (includes MySQL settings for HLStatsX if different)
$hlstatsx_config = "{$hlstatsx_root}/heatmaps/config.inc.php";

// Cache directory to stash temporary files. This should be inaccessible via your Web server!
$cachepath = "{$rootpath}/cache";

// Cache method. Options are: "json", "phpFastCache"
$cache_method = "json";

// Old versions and maps that I just don't want in the list
// New system is to use 
//{$datapath}/thirdparty/maps-blacklist.txt
$excludemaps = array(
	'amber_spirits_coop_beta3',
	'amber_spirits_coop_beta4',
	'amber_spirits_coop_beta5',
	'amber_spirits_coop_beta6',
	'angle_iron_coop_beta2',
	'angle_iron_coop_beta3',
	'angle_iron_coop_beta4',
	'battle_sdk_example',
	'block_party_coop_beta4',
	'bridge_coop_b3',
	'bunker_busting_coopv1_2',
	'bunker_busting_coopv1_3',
	'caves_coop1',
	'clean_sweep_coop_beta2',
	'contact_coop_oldv1',
	'district_coop_oldv1',
	'fortress_coop_beta1',
	'fortress_coop_beta2',
	'fortress_coop_beta3',
	'fortress_coop_beta4',
	'game_day_coopv1_2',
	'goldeneye_facility_coop',
	'heights_coop_oldv1',
	'hijacked_b2',
	'ins_prison_b3',
	'jail_break_coopv1_1',
	'kandagal_b3',
	'kandagal_coop_b3',
	'launch_control_coopv1_4',
	'launch_control_coopv1_5',
	'launch_control_coopv1_6',
	'market_coop_oldv1',
	'ministry_coop_oldv1',
	'mout',
	'sdk_coop',
	'siege_coop_oldv1',
	'tell_coop_v2',
	'tell_v1',
	'the_burbs_coop_beta5',
	'training',
	'tunnel_rats_coopv1_4',
	'warehouse_coop_Alpha5_2B',
	'warehouse_coop_beta_1'
);

// HLStatsX tables and fields
$tables = array(
        'Games_Defaults' => array(
                'allfields'     => array('code', 'parameter', 'value'),
                'fields'        => array('parameter')
        ),
        'Heatmap_Config' => array(
                'allfields'     => array('game','map','xoffset','yoffset','flipx','flipy','rotate','days','brush','scale','font','thumbw','thumbh','cropx1','cropy1','cropx2','cropy2'),
                'fields'        => array('xoffset','yoffset','flipx','flipy','rotate','days','brush','scale','font','thumbw','thumbh','cropx1','cropy1','cropx2','cropy2')
        ),
        'Actions' => array(
                'allfields'     => array('game', 'code', 'reward_player', 'reward_team', 'team', 'description', 'for_PlayerActions', 'for_PlayerPlayerActions', 'for_TeamActions', 'for_WorldActions'),
                'fields'        => array('code')
        ),
        'Ranks' => array(
                'allfields'     => array('game','image','minKills','maxKills','rankName'),
                'fields'        => array('rankName')
        ),
        'Awards' => array(
                'allfields'     => array('game', 'code', 'name', 'verb'),
                'fields'        => array('name', 'verb')
        ),
        'Ribbons' => array(
                'allfields'     => array('game', 'awardCode', 'awardCount', 'special', 'image', 'ribbonName'),
                'fields'        => array('image', 'ribbonName')
        ),
        'Weapons' => array(
                'allfields'     => array('game', 'code', 'name', 'modifier'),
                'fields'        => array('name')
        ),
        'Teams' => array(
                'allfields'     => array('game', 'code', 'name', 'hidden', 'playerlist_bgcolor', 'playerlist_color', 'playerlist_index'),
                'fields'        => array('name')
        ),
        'Roles' => array(
                'allfields'     => array('game', 'code', 'name'),
                'fields'        => array('name')
        )
);
// Stats tables to be displayed
$stats_tables = array(
	'Weapons' => array(
		'fields' => array(
			'Name' => 1,
			'Class' => 0,
			'CR' => 0,
			'Length' => 0,
			'Cost' => 1,
			'Slot' => 0,
			'Weight' => 0,
			'RPM' => 1,
			'Fire Modes' => 0,
			'Damage' => 1,
			'DamageChart' => 1,
			'Spread' => 1,
			'Recoil' => 1,
			'Sway' => 0,
			'Ammo' => 1,
			'Magazine' => 1,
			'Carry' => 1,
			'Carry Max' => 0,
			'Upgrades' => 1
		)
	),
	'Upgrades' => array(
		'fields' => array(
			'Name' => 1,
			'Slot' => 0,
			'CR' => 0,
			'Cost' => 1,
			'Ammo Type' => 1,
			'Abilities' => 0,
			'Weapons' => 1
		)
	),
	'Ammo' => array(
		'fields' => array(
			'Name' => 1,
			'Carry' => 0,
			'Mag' => 0,
			'Damage' => 1,
			'DamageGraph' => 1,
			'PenetrationPower' => 1,
			'PenetrationGraph' => 1,
			'Tracer' => 0,
			'Suppression' => 1,
			'DamageHitgroups' => 1
		)
	),
	'Explosives' => array(
		'fields' => array(
			'Name' => 1,
			'Class' => 0,
			'FuseTime' => 1,
			'Cookable' => 1,
			'Speed' => 0,
			'Damage' => 1,
			'DamageGraph' => 1
		)
	),
	'Gear' => array(
		'fields' => array(
			'Name' => 1,
			'Team' => 0,
			'Slot' => 0,
			'Cost' => 1,
			'Weight' => 0,
			'Ammo' => 1,
			'DamageHitgroups' => 1
		)
	),
	'Teams' => array(),
	'Classes' => array(
		'fields' => array(
			'Name' => 1,
			'Team' => 1,
			'Models' => 1,
			'Buy order' => 1,
			'Allowed Items' => 1
		)
	)
);
$class_name_remove = array(
	"1",
	"2",
	"american",
	"british",
	"coop",
	"elimination",
	"german",
	"insurgent",
	"security",
	"template",
	"training"
);
// Include the private config (never updated by Git) to override or set other variables
$cfg_private="{$includepath}/config.private.php";
if (file_exists($cfg_private)) {
	require_once($cfg_private);
} else {
	file_put_contents($cfg_private,"<?php\n//Custom Config for your site - this will not be modified by the tools!\n\n");
}
