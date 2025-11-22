<?php
/**
 * Plugin Name: Coffee Menu Board
 * Description: Admin-manageable coffee menu board with responsive flip-board styling. Use shortcode: [coffee_menu_board title="Coffee Menu" badge_text="Best Seller!"]
 * Version:     1.0.0
 * Author:      Your Name
 * License:     GPL-2.0+
 * Text Domain: coffee-menu-board
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Coffee_Menu_Board_Plugin {
    const CPT = 'cmb_item';
    const META_PRICE = '_cmb_price';
    const META_BEST  = '_cmb_best_seller';

    public function __construct() {
        add_action( 'init', [ $this, 'register_cpt' ] );
        add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
        add_action( 'save_post', [ $this, 'save_meta' ] );
        add_filter( 'manage_edit-' . self::CPT . '_columns', [ $this, 'admin_columns' ] );
        add_action( 'manage_' . self::CPT . '_posts_custom_column', [ $this, 'admin_columns_content' ], 10, 2 );
        add_filter( 'manage_edit-' . self::CPT . '_sortable_columns', [ $this, 'sortable_columns' ] );
        add_action( 'pre_get_posts', [ $this, 'admin_orderby' ] );

        add_shortcode( 'coffee_menu_board', [ $this, 'shortcode' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );

        register_activation_hook( __FILE__, [ $this, 'activate' ] );
    }

    public function activate() {
        $this->register_cpt();
        flush_rewrite_rules();
    }

    public function register_cpt() {
        $labels = [
            'name'               => __('Menu Items','coffee-menu-board'),
            'singular_name'      => __('Menu Item','coffee-menu-board'),
            'add_new'            => __('Add New Item','coffee-menu-board'),
            'add_new_item'       => __('Add New Menu Item','coffee-menu-board'),
            'edit_item'          => __('Edit Menu Item','coffee-menu-board'),
            'new_item'           => __('New Menu Item','coffee-menu-board'),
            'view_item'          => __('View Menu Item','coffee-menu-board'),
            'search_items'       => __('Search Menu Items','coffee-menu-board'),
            'not_found'          => __('No menu items found','coffee-menu-board'),
            'menu_name'          => __('Coffee Menu','coffee-menu-board'),
        ];
        register_post_type( self::CPT, [
            'labels'        => $labels,
            'public'        => false,
            'show_ui'       => true,
            'show_in_menu'  => true,
            'menu_position' => 20,
            'menu_icon'     => 'dashicons-list-view',
            'supports'      => ['title','page-attributes'], // title = item name, page-attributes = ordering
            'capability_type' => 'post',
            'map_meta_cap'  => true,
        ] );
    }

    public function add_meta_boxes() {
        add_meta_box(
            'cmb_item_meta',
            __('Menu Item Details','coffee-menu-board'),
            [ $this, 'render_meta_box' ],
            self::CPT,
            'normal',
            'high'
        );
    }

    public function render_meta_box( $post ) {
        wp_nonce_field( 'cmb_item_meta_save', 'cmb_item_meta_nonce' );
        $price = get_post_meta( $post->ID, self::META_PRICE, true );
        $best  = (bool) get_post_meta( $post->ID, self::META_BEST, true );
        ?>
        <p>
            <label for="cmb_price"><strong><?php _e('Price','coffee-menu-board'); ?></strong></label><br>
            <input type="text" id="cmb_price" name="cmb_price" value="<?php echo esc_attr( $price ); ?>" placeholder="e.g., 3.20" style="max-width:200px">
            <span class="description"><?php _e('Numbers only. Currency symbol added on display.','coffee-menu-board'); ?></span>
        </p>
        <p>
            <label>
                <input type="checkbox" name="cmb_best" <?php checked( $best ); ?> />
                <?php _e('Mark as Best Seller','coffee-menu-board'); ?>
            </label>
        </p>
        <p class="description"><?php _e('Use “Order” box in the right sidebar to set display order (lower number appears first).','coffee-menu-board'); ?></p>
        <?php
    }

    public function save_meta( $post_id ) {
        if ( ! isset( $_POST['cmb_item_meta_nonce'] ) || ! wp_verify_nonce( $_POST['cmb_item_meta_nonce'], 'cmb_item_meta_save' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        $price = isset($_POST['cmb_price']) ? preg_replace('/[^0-9.\-]/','', $_POST['cmb_price']) : '';
        update_post_meta( $post_id, self::META_PRICE, $price );

        $best  = ! empty($_POST['cmb_best']) ? 1 : 0;
        update_post_meta( $post_id, self::META_BEST, $best );
    }

    /* Admin list columns */
    public function admin_columns( $cols ) {
        $new = [];
        $new['cb'] = $cols['cb'];
        $new['title'] = __('Item','coffee-menu-board');
        $new['cmb_price'] = __('Price','coffee-menu-board');
        $new['cmb_best']  = __('Best Seller','coffee-menu-board');
        $new['menu_order'] = __('Order','coffee-menu-board');
        $new['date'] = $cols['date'];
        return $new;
    }
    public function admin_columns_content( $col, $post_id ) {
        if ( 'cmb_price' === $col ) {
            $p = get_post_meta( $post_id, self::META_PRICE, true );
            echo $p !== '' ? esc_html( $this->format_price( $p ) ) : '—';
        } elseif ( 'cmb_best' === $col ) {
            echo get_post_meta( $post_id, self::META_BEST, true ) ? '⭐' : '—';
        } elseif ( 'menu_order' === $col ) {
            $post = get_post( $post_id );
            echo intval( $post->menu_order );
        }
    }
    public function sortable_columns( $cols ) {
        $cols['menu_order'] = 'menu_order';
        return $cols;
    }
    public function admin_orderby( $q ) {
        if ( is_admin() && $q->is_main_query() && $q->get('post_type') === self::CPT && ! $q->get('orderby') ) {
            $q->set('orderby','menu_order title');
            $q->set('order','ASC');
        }
    }

    /* Assets */
    public function register_assets() {
        $handle = 'cmb-frontend';
        wp_register_style( $handle, false, [], '1.0' );
        wp_add_inline_style( $handle, $this->frontend_css() );
        // Enqueue only when shortcode is present via wp_enqueue_scripts hook from shortcode handler (fallback here to allow forced enqueue)
    }

    private function frontend_css() {
        return <<<CSS
:root{
  --board-bg:#111;
  --board-edge:#151515;
  --line:#2c2c2c;
  --text:#ffcc40;
  --muted:#ffcc40;
  --accent:#ff5333;
  --shadow:0 10px 24px rgba(0,0,0,.45);
  --radius:10px;
  --row-font:clamp(14px, 3.8vw, 18px);
  --title-font:clamp(14px, 4.2vw, 18px);
  --row-pad-y:12px;
}
.cmb-wrap{display:grid;place-items:center}
.cmb-board{
  position:relative;
  /* width:min(92vw,560px); */
  width:100%;
  background:linear-gradient(#101010,#0d0d0d);
  border:1px solid var(--board-edge);
  border-radius:var(--radius);
  padding:18px 18px 24px;
  box-shadow:var(--shadow);
  color:var(--text);
  font-family:"Courier New",ui-monospace,Menlo,Consolas,monospace;
}
.cmb-title{
  margin:0 0 8px;
  text-align:center;
  letter-spacing:.12em;
  color:#e9e9e9;
  font-size:var(--title-font);
  text-transform:uppercase;
}
.cmb-table{
  width:100%;
  border-collapse:collapse;
  text-transform:uppercase;
  font-size:var(--row-font);
  font-variant-numeric:tabular-nums;
}
.cmb-sr{position:absolute!important;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;}
.cmb-table tbody tr{
  display:grid;
  grid-template-columns:1fr auto;
  align-items:center;
  gap:14px;
  padding: var(--row-pad-y) 2px;
  border-bottom:1px solid var(--line);
}
.cmb-table td:first-child{letter-spacing:.12em}
.cmb-table td:last-child{justify-self:end;color:var(--muted)}
.cmb-best td{color:#ffd27a;font-weight:700}
.cmb-badge{
  position:absolute; right:-18px; top:-18px;
  width:92px;height:92px;background:var(--accent);color:#fff;border-radius:50%;
  display:grid;place-items:center;font-weight:700;text-align:center;line-height:1.05;padding:10px;
  transform:rotate(10deg); box-shadow:0 10px 18px rgba(0,0,0,.32);
}
@media (max-width:520px){
  .cmb-board{padding:14px 14px 18px}
  .cmb-table tbody tr{grid-template-columns:1fr;gap:6px}
  .cmb-table td:last-child{justify-self:start;opacity:.9}
  .cmb-badge{right:8px;top:8px;width:74px;height:74px;font-size:12px;transform:rotate(8deg)}
}
CSS;
    }

    /* Shortcode */
    public function shortcode( $atts ) {
        $atts = shortcode_atts( [
            'title'      => 'Coffee Menu',
            'badge_text' => 'Best Seller!',
            'show_badge' => '1',
            'class'      => '',
            'category'   => '', // optional future taxonomy
        ], $atts, 'coffee_menu_board' );

        // Query items (ordered by menu_order then title)
        $args = [
            'post_type'      => self::CPT,
            'posts_per_page' => -1,
            'orderby'        => ['menu_order' => 'ASC', 'title' => 'ASC'],
            'order'          => 'ASC',
            'post_status'    => 'publish',
        ];
        $items = get_posts( $args );

        // Enqueue styles now that the shortcode is used
        wp_enqueue_style( 'cmb-frontend' );

        ob_start(); ?>
        <div class="cmb-wrap <?php echo esc_attr( $atts['class'] ); ?>">
          <div class="cmb-board" role="region" aria-label="<?php echo esc_attr( $atts['title'] ); ?>">
            <h2 class="cmb-title"><?php echo esc_html( $atts['title'] ); ?></h2>

            <table class="cmb-table" aria-describedby="cmb-desc-<?php echo esc_attr( wp_unique_id('') ); ?>">
              <caption id="cmb-desc-<?php echo esc_attr( wp_unique_id('') ); ?>" class="cmb-sr"><?php echo esc_html( $atts['title'] ); ?></caption>
              <thead class="cmb-sr">
                <tr>
                  <th scope="col"><?php esc_html_e( 'Drink', 'coffee-menu-board' ); ?></th>
                  <th scope="col"><?php esc_html_e( 'Price', 'coffee-menu-board' ); ?></th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ( $items as $item ) :
                  $price = get_post_meta( $item->ID, self::META_PRICE, true );
                  $best  = get_post_meta( $item->ID, self::META_BEST, true );
                  $classes = $best ? 'cmb-best' : '';
              ?>
                <tr class="<?php echo esc_attr( $classes ); ?>">
                  <td><?php echo esc_html( get_the_title( $item ) ); ?></td>
                  <td><?php echo $price !== '' ? esc_html( $this->format_price( $price ) ) : '&nbsp;'; ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>

            <?php if ( $this->has_best_seller( $items ) && $atts['show_badge'] === '1' ) : ?>
              <div class="cmb-badge" aria-label="<?php echo esc_attr( $atts['badge_text'] ); ?>">
                <?php echo esc_html( $atts['badge_text'] ); ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function has_best_seller( $items ) {
        foreach ( $items as $item ) {
            if ( get_post_meta( $item->ID, self::META_BEST, true ) ) return true;
        }
        return false;
    }

    private function format_price( $raw ) {
        // Accept 2.3 -> 2.30; 3 -> 3.00, no currency symbol stored in DB
        $num = floatval( $raw );
        $formatted = number_format( $num, 2, '.', '' );
        // Use site currency symbol if WooCommerce exists; else default $
        $symbol = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$';
        return $symbol . $formatted;
    }
}
new Coffee_Menu_Board_Plugin();