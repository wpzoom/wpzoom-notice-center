<?php
/**
 * WPZOOM Notice Center – module loader.
 *
 * Include this file from your plugin/theme, then call set_assets() with your
 * base URL so the module can enqueue its CSS/JS. Use as a git submodule in
 * each product repo.
 *
 * @package WPZOOM_Notice_Center
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;


if ( ! class_exists( 'WPZOOM_Notice_Center' ) ) {

	/**
	 * WPZOOM Notice Center class.
	 */
	class WPZOOM_Notice_Center {

		const VERSION = '1.0.0';

		private static $instance = null;

		private $notices = array();

		private $collected = false;

		private $meta_key = 'wpzoom_notice_center_dismissed';

		private $assets = array(
			'css_url' => '',
			'js_url'  => '',
		);

		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		private function __construct() {
			if ( ! is_admin() ) {
				return;
			}

			add_action( 'current_screen', array( $this, 'collect_notices' ), 20 );
			add_action( 'admin_notices', array( $this, 'render' ), 5 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
			add_action( 'wp_ajax_wpzoom_notice_center_dismiss', array( $this, 'ajax_dismiss' ) );
		}

		public function set_assets( $assets ) {
			$this->assets = wp_parse_args( $assets, $this->assets );
			return $this;
		}

		public function collect_notices() {
			if ( $this->collected ) {
				return;
			}
			$this->collected = true;

			$raw_notices = apply_filters( 'wpzoom_notice_center_notices', array() );

			if ( ! is_array( $raw_notices ) || empty( $raw_notices ) ) {
				return;
			}

			$current_screen = get_current_screen();
			$screen_id      = $current_screen ? $current_screen->id : '';

			foreach ( $raw_notices as $notice ) {
				if ( empty( $notice['id'] ) ) {
					continue;
				}

				$cap = ! empty( $notice['capability'] ) ? $notice['capability'] : 'manage_options';
				if ( ! current_user_can( $cap ) ) {
					continue;
				}

				if ( ! empty( $notice['screens'] ) && is_array( $notice['screens'] ) ) {
					if ( ! in_array( $screen_id, $notice['screens'], true ) ) {
						continue;
					}
				}

				if ( $this->is_dismissed( $notice['id'] ) ) {
					continue;
				}

				if ( ! empty( $notice['conditions'] ) && is_callable( $notice['conditions'] ) ) {
					if ( ! call_user_func( $notice['conditions'] ) ) {
						continue;
					}
				}

				$notice = wp_parse_args( $notice, array(
					'content'          => '',
					'icon'             => '',
					'icon_type'        => '',
					'primary_button'   => array(),
					'secondary_button' => array(),
					'source'           => '',
					'priority'         => 10,
					'slide_timeout'    => 6,
				) );

				if ( is_array( $notice['icon'] ) ) {
					$notice['icon'] = wp_parse_args( $notice['icon'], array(
						'type'             => 'image',
						'src'              => '',
						'dashicon'         => '',
						'color'            => '',
						'background_color' => '',
					) );
					if ( empty( $notice['icon_type'] ) ) {
						$notice['icon_type'] = ( 'dashicon' === $notice['icon']['type'] ) ? 'dashicon' : 'img';
					}
				} elseif ( ! empty( $notice['icon'] ) && empty( $notice['icon_type'] ) ) {
					$notice['icon_type'] = ( 0 === strpos( $notice['icon'], 'dashicons-' ) ) ? 'dashicon' : 'img';
				}

				$this->notices[] = $notice;
			}

			usort( $this->notices, function ( $a, $b ) {
				return intval( $a['priority'] ) - intval( $b['priority'] );
			} );
		}

		public function render() {
			$this->collect_notices();

			$count = count( $this->notices );

			if ( 0 === $count ) {
				return;
			}

			if ( 1 === $count ) {
				$this->render_single_notice( $this->notices[0] );
				return;
			}

			$this->render_carousel( $this->notices );
		}

		private function render_single_notice( $notice ) {
			?>
			<div class="notice wpzoom-notice-single" data-notice-id="<?php echo esc_attr( $notice['id'] ); ?>">
				<div class="wpzoom-notice-single__inner">
					<?php $this->render_icon( $notice ); ?>
					<div class="wpzoom-notice-single__body">
						<?php if ( ! empty( $notice['heading'] ) ) : ?>
							<h3><?php echo esc_html( $notice['heading'] ); ?></h3>
						<?php endif; ?>
						<?php if ( ! empty( $notice['content'] ) ) : ?>
							<div class="wpzoom-notice-single__content"><?php echo wp_kses_post( $notice['content'] ); ?></div>
						<?php endif; ?>
						<?php $this->render_buttons( $notice ); ?>
					</div>
				</div>
				<button type="button" class="notice-dismiss wpzoom-nc-dismiss-single" data-notice-id="<?php echo esc_attr( $notice['id'] ); ?>">
					<span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'wpzoom-notice-center' ); ?></span>
				</button>
			</div>
			<?php
		}

		private function render_carousel( $notices ) {
			$total = count( $notices );
			?>
			<div id="wpzoom-notice-center" class="notice wpzoom-notice-center" data-total="<?php echo (int) $total; ?>">

				<div class="wpzoom-nc-header">
					<div class="wpzoom-nc-header__left">
						<span class="wpzoom-nc-header__title"><span style="color: #2b6;">•</span> <?php esc_html_e( 'WPZOOM Notice Center', 'instagram-widget-by-wpzoom' ); ?></span>
						<span class="wpzoom-nc-header__subtitle"><?php esc_html_e( 'Stay updated with the latest from WPZOOM products and services!', 'wpzoom-notice-center' ); ?></span>
					</div>
					<div class="wpzoom-nc-header__right">
						<button type="button" class="wpzoom-nc-dismiss-slide" title="<?php esc_attr_e( 'Dismiss this notice', 'wpzoom-notice-center' ); ?>">
							<span class="dashicons dashicons-no-alt"></span>
						</button>
					</div>
				</div>

				<div class="wpzoom-nc-body">
					<div class="wpzoom-nc-slides-viewport">
						<div class="wpzoom-nc-slides-track">
							<?php foreach ( $notices as $index => $notice ) : ?>
								<?php $timeout = isset( $notice['slide_timeout'] ) ? max( 1, (int) $notice['slide_timeout'] ) : 6; ?>
								<div class="wpzoom-nc-slide<?php echo 0 === $index ? ' wpzoom-nc-slide--active' : ''; ?>"
									 data-slide-index="<?php echo (int) $index; ?>"
									 data-notice-id="<?php echo esc_attr( $notice['id'] ); ?>"
									 data-slide-timeout="<?php echo (int) $timeout; ?>">
									<div class="wpzoom-nc-slide__inner">
										<?php $this->render_icon( $notice, 'wpzoom-nc-slide__icon' ); ?>
										<div class="wpzoom-nc-slide__content">
											<?php if ( ! empty( $notice['heading'] ) ) : ?>
												<h3 class="wpzoom-nc-slide__heading"><?php echo esc_html( $notice['heading'] ); ?></h3>
											<?php endif; ?>
											<?php if ( ! empty( $notice['content'] ) ) : ?>
												<div class="wpzoom-nc-slide__text"><?php echo wp_kses_post( $notice['content'] ); ?></div>
											<?php endif; ?>
											<?php $this->render_buttons( $notice ); ?>
										</div>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				</div>

				<div class="wpzoom-nc-footer">
					<div class="wpzoom-nc-dots">
						<?php for ( $i = 0; $i < $total; $i++ ) : ?>
							<button type="button"
									class="wpzoom-nc-dot<?php echo 0 === $i ? ' wpzoom-nc-dot--active' : ''; ?>"
									data-slide-index="<?php echo (int) $i; ?>"></button>
						<?php endfor; ?>
					</div>
				</div>
			</div>
			<?php
		}

		private function render_icon( $notice, $css_class = 'wpzoom-notice-single__icon' ) {
			if ( empty( $notice['icon'] ) ) {
				return;
			}

			$icon    = $notice['icon'];
			$is_obj  = is_array( $icon );
			$type    = $is_obj ? ( ! empty( $icon['type'] ) ? $icon['type'] : 'image' ) : $notice['icon_type'];
			$style   = '';
			if ( $is_obj && ( ! empty( $icon['color'] ) || ! empty( $icon['background_color'] ) ) ) {
				$parts = array();
				if ( ! empty( $icon['color'] ) ) {
					$parts[] = 'color: ' . esc_attr( $icon['color'] );
				}
				if ( ! empty( $icon['background_color'] ) ) {
					$parts[] = 'background-color: ' . esc_attr( $icon['background_color'] );
				}
				$style = ' style="' . implode( '; ', $parts ) . '"';
			}
			$dashicon = $is_obj ? ( ! empty( $icon['dashicon'] ) ? $icon['dashicon'] : '' ) : ( is_string( $icon ) ? $icon : '' );
			$img_src  = $is_obj ? ( ! empty( $icon['src'] ) ? $icon['src'] : '' ) : ( is_string( $icon ) ? $icon : '' );
			?>
			<div class="<?php echo esc_attr( $css_class ); ?>"<?php echo $style; ?>>
				<?php if ( 'dashicon' === $type && $dashicon ) : ?>
					<span class="dashicons <?php echo esc_attr( $dashicon ); ?>"></span>
				<?php elseif ( $img_src ) : ?>
					<img src="<?php echo esc_url( $img_src ); ?>" alt="" />
				<?php endif; ?>
			</div>
			<?php
		}

		private function render_buttons( $notice ) {
			$has_primary   = ! empty( $notice['primary_button']['label'] );
			$has_secondary = ! empty( $notice['secondary_button']['label'] );

			if ( ! $has_primary && ! $has_secondary ) {
				return;
			}
			?>
			<div class="wpzoom-nc-buttons">
				<?php if ( $has_primary ) : ?>
					<a href="<?php echo esc_url( $notice['primary_button']['url'] ); ?>"
					   class="button button-primary"
					   <?php echo ! empty( $notice['primary_button']['new_tab'] ) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
						<?php echo esc_html( $notice['primary_button']['label'] ); ?>
					</a>
				<?php endif; ?>

				<?php if ( $has_secondary ) :
					$sec_url    = ! empty( $notice['secondary_button']['url'] ) ? $notice['secondary_button']['url'] : '#';
					$is_dismiss = empty( $notice['secondary_button']['url'] );
					?>
					<a href="<?php echo esc_url( $sec_url ); ?>"
					   class="button button-secondary<?php echo $is_dismiss ? ' wpzoom-nc-dismiss-single' : ''; ?>"
					   <?php echo $is_dismiss ? 'data-notice-id="' . esc_attr( $notice['id'] ) . '"' : ''; ?>
					   <?php echo ! empty( $notice['secondary_button']['new_tab'] ) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
						<?php echo esc_html( $notice['secondary_button']['label'] ); ?>
					</a>
				<?php endif; ?>
			</div>
			<?php
		}

		public function enqueue_assets() {
			$this->collect_notices();

			if ( empty( $this->notices ) ) {
				return;
			}

			if ( ! empty( $this->assets['css_url'] ) ) {
				wp_enqueue_style(
					'wpzoom-notice-center',
					$this->assets['css_url'],
					array( 'dashicons' ),
					self::VERSION
				);
			}

			if ( ! empty( $this->assets['js_url'] ) ) {
				wp_enqueue_script(
					'wpzoom-notice-center',
					$this->assets['js_url'],
					array(),
					self::VERSION,
					true
				);

				wp_localize_script( 'wpzoom-notice-center', 'wpzoomNoticeCenterData', array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'wpzoom_notice_center_nonce' ),
				) );
			}
		}

		public function ajax_dismiss() {
			check_ajax_referer( 'wpzoom_notice_center_nonce', 'nonce' );

			$user_id = get_current_user_id();
			if ( ! $user_id ) {
				wp_send_json_error( 'Not logged in.' );
			}

			$dismissed = get_user_meta( $user_id, $this->meta_key, true );
			if ( ! is_array( $dismissed ) ) {
				$dismissed = array();
			}

			$dismiss_all = isset( $_POST['dismiss_all'] ) && 'true' === sanitize_text_field( wp_unslash( $_POST['dismiss_all'] ) );

			if ( $dismiss_all ) {
				$raw_notices = apply_filters( 'wpzoom_notice_center_notices', array() );
				foreach ( $raw_notices as $n ) {
					if ( ! empty( $n['id'] ) && ! in_array( $n['id'], $dismissed, true ) ) {
						$dismissed[] = sanitize_text_field( $n['id'] );
					}
				}
			} else {
				$notice_id = isset( $_POST['notice_id'] ) ? sanitize_text_field( wp_unslash( $_POST['notice_id'] ) ) : '';
				if ( ! empty( $notice_id ) && ! in_array( $notice_id, $dismissed, true ) ) {
					$dismissed[] = $notice_id;
				}
			}

			update_user_meta( $user_id, $this->meta_key, $dismissed );
			wp_send_json_success();
		}

		private function is_dismissed( $notice_id ) {
			$user_id   = get_current_user_id();
			$dismissed = get_user_meta( $user_id, $this->meta_key, true );

			return is_array( $dismissed ) && in_array( $notice_id, $dismissed, true );
		}

		public function get_notice_count() {
			$this->collect_notices();
			return count( $this->notices );
		}
	}

	WPZOOM_Notice_Center::get_instance();
}