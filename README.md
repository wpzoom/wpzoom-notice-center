# WPZOOM Notice Center (module)

Admin notice carousel for WPZOOM plugins/themes. Use as a **git submodule** in each product repo so all products share the same code.

## Add as submodule to a plugin

From your plugin repo root (e.g. `wpzoom-forms`):

```bash
git submodule add <url-of-this-repo> wpzoom-notice-center
```

## Load from your plugin

In your main plugin file, load the module only in the admin and set asset URLs to the submodule path:

```php
// WPZOOM Notice Center (submodule).
$wpz_notice_center_path = plugin_dir_path( __FILE__ ) . 'wpzoom-notice-center/';
$wpz_notice_center_url  = plugin_dir_url( __FILE__ ) . 'wpzoom-notice-center/';

if ( is_admin() && ! class_exists( 'WPZOOM_Notice_Center' ) && file_exists( $wpz_notice_center_path . 'notice-center.php' ) ) {
	require_once $wpz_notice_center_path . 'notice-center.php';
	WPZOOM_Notice_Center::get_instance()->set_assets( array(
		'css_url' => $wpz_notice_center_url . 'assets/notice-center.css',
		'js_url'  => $wpz_notice_center_url . 'assets/notice-center.js',
	) );
}
```
## Register notices

Use the `wpzoom_notice_center_notices` filter:

```php
add_filter( 'wpzoom_notice_center_notices', function ( $notices ) {
	$notices[] = array(
		'id'               => 'my_notice_id',
		'heading'          => 'Title',
		'content'          => '<p>HTML content.</p>',
		'icon'             => array( 'type' => 'dashicon', 'dashicon' => 'dashicons-megaphone' ),
		'primary_button'   => array( 'label' => 'Action', 'url' => 'https://...', 'new_tab' => true ),
		'secondary_button' => array( 'label' => 'Later', 'url' => '' ),
		'capability'       => 'manage_options',
		'screens'          => array( 'dashboard' ),
		'priority'         => 10,
		'slide_timeout'    => 6,
	);
	return $notices;
} );
```

## Module layout

```
wpzoom-notice-center/
├── notice-center.php    ← entry point (require this from your plugin)
├── assets/
│   ├── notice-center.css
│   └── notice-center.js
└── README.md
```
