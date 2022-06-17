<?php

/*
 * Plugin Name: ku_carousel
 * Plugin URI: https://xayrin.com/plugins
 * Description: A carousel
 * Author: Scott A. Dixon
 * Author URI: https://xayrin.com
 * Version: 1.0.0
 */

defined('ABSPATH') or die('nope');

// defines      

define('_PLUGIN_KUCAROUSEL', 'ku_carousel');

define('_URL_KUCAROUSEL', plugin_dir_url(__FILE__));
define('_PATH_KUCAROUSEL', plugin_dir_path(__FILE__));

define('_ARGS_KUCAROUSEL', [
	'ku_carousel-active-field' => [
		'type' => 'string',
		'default' => 'yes'
	],
	'ku_carousel-css-field' => [
		'type' => 'string',
		'default' => ''
	],
	'ku_carousel-js-field' => [
		'type' => 'string',
		'default' => ''
	]
]);

// classes

class kucarousel_API {
	public function add_routes() {
		register_rest_route(_PLUGIN_KUCAROUSEL . '-plugin-api/v1', '/settings', [
				'methods' => 'POST',
				'callback' => [$this, 'update_settings'],
				'args' => kucarousel_Settings::args(),
				'permission_callback' => [$this, 'permissions']
			]
		);
		register_rest_route(_PLUGIN_KUCAROUSEL . '-plugin-api/v1', '/settings', [
				'methods' => 'GET',
				'callback' => [$this, 'get_settings'],
				'args' => [],
				'permission_callback' => [$this, 'permissions']
			]
		);
	}

	public function permissions() {
		return current_user_can('manage_options');
	}

	public function update_settings(WP_REST_Request $request) {
		$settings = [];
		foreach (kucarousel_Settings::args() as $key => $val) {
			$settings[$key] = $request->get_param($key);
		}
		kucarousel_Settings::save_settings($settings);
		return rest_ensure_response(kucarousel_Settings::get_settings());
	}

	public function get_settings(WP_REST_Request $request) {
		return rest_ensure_response(kucarousel_Settings::get_settings());
	}
}

class kucarousel_Settings {
	protected static $option_key = _PLUGIN_KUCAROUSEL . '-settings';

	public static function args() {
		$args = _ARGS_KUCAROUSEL;
		foreach (_ARGS_KUCAROUSEL as $key => $val) {
			$val['required'] = true;
			switch ($val['type']) {
				case 'integer': {
					$cb = 'absint';
					break;
				}
				default: {
					$cb = 'sanitize_text_field';
				}
				$val['sanitize_callback'] = $cb;
			}
		}
		return $args;
	}

	public static function get_settings() {
		$defaults = [];
		foreach (_ARGS_KUCAROUSEL as $key => $val) {
			$defaults[$key] = $val['default'];
		}
		$saved = get_option(self::$option_key, []);
		if (!is_array($saved) || empty($saved)) {
			return $defaults;
		}
		return wp_parse_args($saved, $defaults);
	}

	public static function save_settings(array $settings) {
		$defaults = [];
		foreach (_ARGS_KUCAROUSEL as $key => $val) {
			$defaults[$key] = $val['default'];
		}
		foreach ($settings as $i => $setting) {
			if (!array_key_exists($i, $defaults)) {
				unset($settings[$i]);
			}
		}
		update_option(self::$option_key, $settings);
	}
}

class kucarousel_Menu {
	protected $slug = _PLUGIN_KUCAROUSEL . '-menu';
	protected $assets_url;

	public function __construct($assets_url) {
		$this->assets_url = $assets_url;
		add_action('admin_menu', [$this, 'add_page']);
		add_action('admin_enqueue_scripts', [$this, 'register_assets']);
	}

	public function add_page() {
		add_menu_page(
			_PLUGIN_KUCAROUSEL,
			_PLUGIN_KUCAROUSEL,
			'manage_options',
			$this->slug,
			[$this, 'render_admin'],
			'dashicons-chart-area',
			3
		);

		// add taxonomies menus

		$types = [
			'tab' => 'slide'
		];

		foreach ($types as $type => $child) {
			add_submenu_page(
				$this->slug,
				$type . 's',
				$type . 's',
				'manage_options',
				'/edit-tags.php?taxonomy=' . $type . '&post_type=' . $child
			);
		}

		// add posts menus

		$types = [
			'slide'
		];

		foreach ($types as $type) {
			add_submenu_page(
				$this->slug,
				$type . 's',
				$type . 's',
				'manage_options',
				'/edit.php?post_type=' . $type
			);
		}
	}

	public function register_assets() {
		wp_register_script($this->slug, $this->assets_url . '/' . _PLUGIN_KUCAROUSEL . '.js', ['jquery']);
		wp_register_style($this->slug, $this->assets_url . '/' . _PLUGIN_KUCAROUSEL . '.css');

		wp_localize_script($this->slug, _PLUGIN_KUCAROUSEL, [
			'strings' => [
				'saved' => 'Settings Saved',
				'error' => 'Error'
			],
			'api' => [
				'url' => esc_url_raw(rest_url(_PLUGIN_KUCAROUSEL . '-plugin-api/v1/settings')),
				'nonce' => wp_create_nonce('wp_rest')
			]
		]);
	}

	public function enqueue_assets() {
		if (!wp_script_is($this->slug, 'registered')) {
			$this->register_assets();
		}

		wp_enqueue_script($this->slug);
		wp_enqueue_style($this->slug);
	}

	public function render_admin() {
		wp_enqueue_media();
		$this->enqueue_assets();
?>
		<div id="ku_carousel-wrap" class="wrap">
			<h1>KU Carousel</h1>
			<p>Configure settings...</p>
			<form id="ku_carousel-form" method="post">
				<nav id="ku_carousel-nav" class="nav-tab-wrapper">
					<a href="#ku_carousel-settings" class="nav-tab">Settings</a>
					<a href="#ku_carousel-css" class="nav-tab">Styles</a>
					<a href="#ku_carousel-js" class="nav-tab">Script</a>
				</nav>
				<div class="tab-content">
					<div id="ku_carousel-settings" class="ku_carousel-tab">
						<div class="form-block">
							<em>Active:</em>
							<label class="switch">
								<input type="checkbox" id="ku_carousel-active-field" name="ku_carousel-active-field" value="yes">
								<span class="slider"></span>
							</label>
						</div>
					</div>
					<div id="ku_carousel-css" class="ku_carousel-tab">
						<div class="form-block">
							<label for="ku_carousel-css-field">CSS</label>
							<textarea id="ku_carousel-css-field" class="code" name="ku_carousel-css-field"></textarea>
						</div>
					</div>
					<div id="ku_carousel-js" class="ku_carousel-tab">
						<div class="form-block">
							<label for="ku_carousel-js-field">Javascript</label>
							<textarea id="ku_carousel-js-field" class="code" name="ku_carousel-js-field"></textarea>
						</div>
					</div>
				</div>
				<div><?php submit_button(); ?></div>
				<div id="ku_carousel-feedback"></div>
			</form>
		</div>
<?php
	}
}

// functions

function kucarousel_init($dir) {
	if (is_admin()) {
		new kucarousel_Menu(_URL_KUCAROUSEL);
	}

	// set up post types

	$types = [
		'slide'
	];

	foreach ($types as $type) {
		$uc_type = ucwords($type);

		$labels = [
			'name' => $uc_type . 's',
			'singular_name' => $uc_type,
			'menu_name' => $uc_type . 's',
			'name_admin_bar' => $uc_type . 's',
			'add_new' => 'Add New',
			'add_new_item' => 'Add New ' . $uc_type,
			'new_item' => 'New ' . $uc_type,
			'edit_item' => 'Edit ' . $uc_type,
			'view_item' => 'View ' . $uc_type,
			'all_items' => $uc_type . 's',
			'search_items' => 'Search ' . $uc_type . 's',
			'not_found' => 'No ' . $uc_type . 's Found'
		];

		register_post_type($type, [
			'supports' => [
				'title',
				'thumbnail',
				'revisions',
				'post-formats'
			],
			'hierarchical' => true,
			'labels' => $labels,
			'show_ui' => true,
			'show_in_menu' => false,
			'query_var' => true,
			'has_archive' => false,
			'rewrite' => ['slug' => $type]
		]);
	}

	// set up taxonomies

	$types = [
		'tab' => 'slide'
	];

	foreach ($types as $type => $child) {
		$uc_type = ucwords($type);

		$labels = [
			'name' => $uc_type . 's',
			'singular_name' => $uc_type,
			'search_items' => 'Search ' . $uc_type . 's',
			'all_items' => 'All ' . $uc_type . 's',
			'parent_item' => 'Parent ' . $uc_type,
			'parent_item_colon' => 'Parent ' . $uc_type . ':',
			'edit_item' => 'Edit ' . $uc_type, 
			'update_item' => 'Update ' . $uc_type,
			'add_new_item' => 'Add New ' . $uc_type,
			'new_item_name' => 'New ' . $uc_type . ' Name',
			'menu_name' => $uc_type . 's',
		];

		register_taxonomy($type, [$child], [
			'hierarchical' => true,
			'labels' => $labels,
			'show_ui' => true,
			'show_in_menu' => false,
			'show_in_rest' => true,
			'show_admin_column' => true,
			'query_var' => true,
			'rewrite' => ['slug' => $type],
		]);
	}
}

function kucarousel_api_init() {
	kucarousel_Settings::args();
	$api = new kucarousel_API();
	$api->add_routes();
}

// custom post stuff

function kucarousel_add_metaboxes() {
	$screens = ['slide'];
	foreach ($screens as $screen) {
		add_meta_box(
			'kucarousel_meta_box',
			'Slide Data',
			'kucarousel_slide_metabox',
			$screen
		);
	}
}

function kucarousel_slide_metabox($post) {
	$prefix = '_kucarousel-slide_';
	$keys = [
		'pid'
	];
	foreach ($keys as $key) {
		$$key = get_post_meta($post->ID, $prefix . $key, true);
	}
	wp_nonce_field(plugins_url(__FILE__), 'wr_plugin_noncename');
	?>
	<style>
		#kucarousel_meta_box label {
			display: inline-block;
			width: 20%;
			font-weight: 700;
			padding-top: 4px;
		}
		#kucarousel_meta_box input,
		#kucarousel_meta_box select,
		#kucarousel_meta_box textarea {
			box-sizing: border-box;
			display: inline-block;
			width: 73%;
			padding: 3px;
			vertical-align: middle;
			margin-top: 10px;
		}
		#kucarousel_meta_box span.desc {
			display: block;
			width: 18%;
			padding-top: 6px;
			clear: both;
			font-style: italic;
			font-size: 12px;
		}
		#kucarousel_meta_box div.middle {
			margin-bottom: 10px;
			padding-bottom: 10px;
			border-bottom: 1px dashed #ddd;
		}
		#kucarousel_meta_box div.top {
			margin-top: 10px;
			margin-bottom: 10px;
			padding-bottom: 10px;
			border-bottom: 1px dashed #ddd;
		}
		#kucarousel_meta_box div.bottom {
			margin-bottom: 0;
			padding-bottom: 0;
			border-bottom: 0;
		}
	</style>
	<div class="inside">
		<div class="top bottom">
			<label>Product:</label>
			<input type="hidden" id="ku-slide-pid" name="_kucarousel-slide_pid" value="<?php echo $pid; ?>">
			<select id="ku-products">
				<option value="0">Select Product...</option>
<?php
	$loop = get_posts([
		'post_type' => 'product',
		'post_status' => 'publish',
		'posts_per_page' => '-1',
		'orderby' => 'title',
		'order' => 'ASC'
	]);

	if (count($loop) > 0) {
		foreach ($loop as $product) {
			$id = $product->ID;
			$selected = ($id == $pid) ? ' selected' : '';
			echo '<option value="' . $id . '"' . $selected . '>' . $product->post_title . '</option>';
		}
	}
?>
			</select>
			<span class="desc">Choose a product to use for this slide from the dropdown</span>
		</div>
	</div>
	<script>
		let select = document.querySelector('#ku-products');
		let pid = document.querySelector('#ku-slide-pid');
		select.addEventListener('change', function() {
			pid.value = this.value;
		});
	</script>
	<?php
}

function kucarousel_save_postdata($post_id) {
	$prefix = '_kucarousel-slide_';
	$keys = [
		'pid'
	];
	foreach ($keys as $key) {
		if (array_key_exists($prefix . $key, $_POST)) {
			update_post_meta(
				$post_id,
				$prefix . $key,
				$_POST[$prefix . $key]
			);
		}
	}
}

// menu stuff

function kucarousel_set_current_menu($parent_file) {
	global $submenu_file, $current_screen, $pagenow;
	$taxonomy = 'tab';

	if ($current_screen->id == 'edit-' . $taxonomy) {
		if ($pagenow == 'post.php') {
			$submenu_file = 'edit.php?post_type=' . $current_screen->post_type;
		}
		if ($pagenow == 'edit-tags.php') {
			$submenu_file = 'edit-tags.php?taxonomy=' . $taxonomy . '&post_type=' . $current_screen->post_type;
		}
		$parent_file = _PLUGIN_KUCAROUSEL . '-menu';
	}
	return $parent_file;
}

// scripts

function kucarousel_admin_scripts() {
	$screen = get_current_screen();

	if (null === $screen) {
		return;
	}
	if ($screen->base !== 'toplevel_page_' . _PLUGIN_KUCAROUSEL . '-menu') {
		return;
	}

	wp_enqueue_code_editor(['type' => 'application/x-httpd-php']);
}

function kucarousel_scripts() {
	//wp_enqueue_script('', '', [], false, false);
}

// shortcode

function kucarousel_shortcode() {
	ob_start();

	$blank = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAIAAACQkWg2AAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAyRpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDcuMS1jMDAwIDc5LmRhYmFjYmIsIDIwMjEvMDQvMTQtMDA6Mzk6NDQgICAgICAgICI+IDxyZGY6UkRGIHhtbG5zOnJkZj0iaHR0cDovL3d3dy53My5vcmcvMTk5OS8wMi8yMi1yZGYtc3ludGF4LW5zIyI+IDxyZGY6RGVzY3JpcHRpb24gcmRmOmFib3V0PSIiIHhtbG5zOnhtcD0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wLyIgeG1sbnM6eG1wTU09Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9tbS8iIHhtbG5zOnN0UmVmPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvc1R5cGUvUmVzb3VyY2VSZWYjIiB4bXA6Q3JlYXRvclRvb2w9IkFkb2JlIFBob3Rvc2hvcCAyMi41IChXaW5kb3dzKSIgeG1wTU06SW5zdGFuY2VJRD0ieG1wLmlpZDpCM0ExQ0JGNTcyRDYxMUVDOTQ4QkQwNDZGRkVFRTk3QiIgeG1wTU06RG9jdW1lbnRJRD0ieG1wLmRpZDpCM0ExQ0JGNjcyRDYxMUVDOTQ4QkQwNDZGRkVFRTk3QiI+IDx4bXBNTTpEZXJpdmVkRnJvbSBzdFJlZjppbnN0YW5jZUlEPSJ4bXAuaWlkOkIzQTFDQkYzNzJENjExRUM5NDhCRDA0NkZGRUVFOTdCIiBzdFJlZjpkb2N1bWVudElEPSJ4bXAuZGlkOkIzQTFDQkY0NzJENjExRUM5NDhCRDA0NkZGRUVFOTdCIi8+IDwvcmRmOkRlc2NyaXB0aW9uPiA8L3JkZjpSREY+IDwveDp4bXBtZXRhPiA8P3hwYWNrZXQgZW5kPSJyIj8+4ad6RQAAABNJREFUeNpiYBgFo2AUwABAgAEAAxAAAbGH3hQAAAAASUVORK5CYII=';

	$carousel = [];
	$custom_terms = get_terms('tab');
?>
<style>
	#ku-carousel {
		font-family: arial, sans-serif;
		position: relative;
		overflow: hidden;
		z-index: 1;
		width: auto;
		margin-bottom: 18px;
		text-align: center;
	}
	.ku-carousel-tabset {
		margin: 0;
		padding: 0 0 3.1764705882em;
	}
	.ku-carousel-tab {
		display: inline-block;
		list-style: none;
		outline: none;
		padding-left: 40px;
		border-bottom: 1px solid #c7c7c7;
		font-size: 17px;
		line-height: 1;
	}
	.ku-carousel-tab:first-child {
		padding-left: 0;
	}
	.ku-carousel-tab a {
		position: relative;
		display: block;
		padding: 9px 0 11px;
		font-weight: 700;
		margin-top: 2px;
		margin-bottom: 4px;
		text-align: left;
	}
	.ku-carousel-tab a:after {
		border-bottom-width: 2px;
		left: 0;
		position: absolute;
		bottom: -5px;
		width: 100%;
		border-bottom: 1px solid transparent;
		content: "";
	}
	.ku-carousel-tab a.active {
		color: #e85f82;
		text-decoration: none;
		cursor: default;
		z-index: 10;
	}
	.ku-carousel-tab a.active:after {
		border-bottom-width: 2px;
		border-bottom-color: #e85f82;
	}
	.ku-carousel-tab-content {
		display: none;
	}
	.ku-carousel-tab-content.active {
		display: block;
	}
	.ku-carousel-tab-content ul {

	}
	.ku-carousel-tab-content ul li {
		display: inline-block;
		list-style: none;
		outline: none;
		margin-right: 15px;
	}
	.ku-carousel-tab-content a {

	}
	.ku-carousel-tab-content img {
		width: 234px;
		height: 234px;
	}
	.ku-carousel-tab-content h3 {

	}
	.ku-carousel-tab-content span {

	}
	.ku-carousel-tab-content p {

	}
</style>
<?php

	foreach($custom_terms as $custom_term) {
		wp_reset_query();
		$args = [
			'post_type' => 'slide',
			'tax_query' => [[
				'taxonomy' => 'tab',
				'field' => 'slug',
				'terms' => $custom_term->slug
			]]
		 ];

		$loop = new WP_Query($args);
		
		if ($loop->have_posts()) {
			$tab = $custom_term->name;
			$items = [];

			while ($loop->have_posts()) : $loop->the_post();
				$pid = get_post_meta(get_the_ID(), '_kucarousel-slide_pid', true);
				$items[] = [
					'title' => get_the_title(),
					'pid' => $pid,
					'product' => get_post($pid)
				];
			endwhile;

			$carousel[] = [
				'tab' => $tab,
				'items' => $items
			];
		}
	}

	if (count($carousel) > 0) {
?>
<div id="ku-carousel">
	<ul class="ku-carousel-tabset">
<?php
		foreach ($carousel as $index => $tab) {
			$active = ($index == 0) ? ' active' : '';
			echo '<li id="ku-carousel-tab-' . $index . '" class="ku-carousel-tab' . $active . '">';
				echo '<a href="#" class="' . trim($active) . '">' . $tab['tab'] . '</a>';
			echo '</li>';
		}
?>
	</ul>
<?php
		foreach ($carousel as $index => $tab) {
			$active = ($index == 0) ? ' active' : '';
			echo '<div id="tab-content-' . $index . '" class="ku-carousel-tab-content' . $active . '">';
				echo '<ul>';

				$items = $tab['items'];
				if (count($items) > 0) {
					foreach ($items as $item) {
						echo '<li>';
							echo '<a href="#">';
								echo '<img class="ext" src="' . $blank . '">';
								echo '<h3>' . $item['title'] . '</h3>';
								echo '<span>' . $item['product']->post_title . '</span>';
								echo '<p>&nbsp;' . $item['product']->post_content . '&nbsp;</p>';
							echo '</a>';
						echo '</li>';
					}
				}

				echo '</ul>';
			echo '</div>';
		}
?>
</div>

<script>
	document.addEventListener('DOMContentLoaded', (event) => {
		$(function(){
			$('.ku-carousel-tab a').on('click', function() {
				$('.ku-carousel-tab').removeClass('active');
				$('.ku-carousel-tab a').removeClass('active');
				$(this).addClass('active');
				$(this).parent().addClass('active');
				var id = $(this).parent().attr('id').slice(16);
				$('.ku-carousel-tab-content').removeClass('active');
				$('#tab-content-' + id).addClass('active');
			});
		});
	});
</script>
<?php
	}

	return ob_get_clean();
}

//     ▄██████▄    ▄██████▄   
//    ███    ███  ███    ███  
//    ███    █▀   ███    ███  
//   ▄███         ███    ███  
//  ▀▀███ ████▄   ███    ███  
//    ███    ███  ███    ███  
//    ███    ███  ███    ███  
//    ████████▀    ▀██████▀   

add_action('init', 'kucarousel_init');
add_action('wp_enqueue_scripts', 'kucarousel_scripts');
add_action('admin_enqueue_scripts', 'kucarousel_admin_scripts');
add_action('rest_api_init', 'kucarousel_api_init');
add_action('add_meta_boxes', 'kucarousel_add_metaboxes');
add_action('save_post', 'kucarousel_save_postdata');

add_filter('parent_file', 'kucarousel_set_current_menu');

add_shortcode('ku_carousel', 'kucarousel_shortcode');

// EOF