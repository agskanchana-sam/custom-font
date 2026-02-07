<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin UI: upload fonts, list them, assign to elements, delete.
 */
class UCF_Admin {

    public function __construct() {
        add_action( 'admin_menu',            array( $this, 'add_menu_page' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_post_ucf_upload_font',    array( $this, 'handle_upload' ) );
        add_action( 'admin_post_ucf_delete_font',    array( $this, 'handle_delete' ) );
        add_action( 'admin_post_ucf_save_assignments', array( $this, 'handle_save_assignments' ) );
    }

    /* ------------------------------------------------------------------ */
    /*  Menu page                                                          */
    /* ------------------------------------------------------------------ */
    public function add_menu_page() {
        add_menu_page(
            __( 'Custom Fonts', 'use-custom-font' ),
            __( 'Custom Fonts', 'use-custom-font' ),
            'manage_options',
            'ucf-custom-fonts',
            array( $this, 'render_page' ),
            'dashicons-editor-textcolor',
            81
        );
    }

    /* ------------------------------------------------------------------ */
    /*  Admin assets                                                       */
    /* ------------------------------------------------------------------ */
    public function enqueue_assets( $hook ) {
        if ( 'toplevel_page_ucf-custom-fonts' !== $hook ) {
            return;
        }
        wp_enqueue_style(
            'ucf-admin-css',
            UCF_PLUGIN_URL . 'assets/admin.css',
            array(),
            UCF_VERSION
        );
    }

    /* ------------------------------------------------------------------ */
    /*  Render admin page                                                  */
    /* ------------------------------------------------------------------ */
    public function render_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'ucf_fonts';
        $fonts = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY font_name ASC, font_weight ASC" );
        $assignments = get_option( 'ucf_font_assignments', array() );

        // Build unique font families for dropdown.
        $families = array();
        foreach ( $fonts as $f ) {
            $families[ $f->font_slug ] = $f->font_name;
        }

        // Notification messages via transient.
        $notice = get_transient( 'ucf_admin_notice' );
        if ( $notice ) {
            delete_transient( 'ucf_admin_notice' );
        }
        ?>
        <div class="wrap ucf-wrap">
            <h1><?php esc_html_e( 'Custom Fonts', 'use-custom-font' ); ?></h1>

            <?php if ( $notice ) : ?>
                <div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
                    <p><?php echo esc_html( $notice['message'] ); ?></p>
                </div>
            <?php endif; ?>

            <!-- Upload form ------------------------------------------------ -->
            <div class="ucf-card">
                <h2><?php esc_html_e( 'Upload a .woff2 Font', 'use-custom-font' ); ?></h2>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
                    <?php wp_nonce_field( 'ucf_upload_font', 'ucf_nonce' ); ?>
                    <input type="hidden" name="action" value="ucf_upload_font">

                    <table class="form-table">
                        <tr>
                            <th><label for="ucf_font_name"><?php esc_html_e( 'Font Family Name', 'use-custom-font' ); ?></label></th>
                            <td><input type="text" id="ucf_font_name" name="ucf_font_name" class="regular-text" required placeholder="e.g. My Custom Font"></td>
                        </tr>
                        <tr>
                            <th><label for="ucf_font_weight"><?php esc_html_e( 'Font Weight', 'use-custom-font' ); ?></label></th>
                            <td>
                                <select id="ucf_font_weight" name="ucf_font_weight">
                                    <option value="100">100 – Thin</option>
                                    <option value="200">200 – Extra Light</option>
                                    <option value="300">300 – Light</option>
                                    <option value="400" selected>400 – Normal</option>
                                    <option value="500">500 – Medium</option>
                                    <option value="600">600 – Semi Bold</option>
                                    <option value="700">700 – Bold</option>
                                    <option value="800">800 – Extra Bold</option>
                                    <option value="900">900 – Black</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="ucf_font_style"><?php esc_html_e( 'Font Style', 'use-custom-font' ); ?></label></th>
                            <td>
                                <select id="ucf_font_style" name="ucf_font_style">
                                    <option value="normal" selected>Normal</option>
                                    <option value="italic">Italic</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="ucf_font_file"><?php esc_html_e( 'Font File (.woff2)', 'use-custom-font' ); ?></label></th>
                            <td><input type="file" id="ucf_font_file" name="ucf_font_file" accept=".woff2" required></td>
                        </tr>
                    </table>

                    <?php submit_button( __( 'Upload Font', 'use-custom-font' ) ); ?>
                </form>
            </div>

            <!-- Uploaded fonts list ---------------------------------------- -->
            <div class="ucf-card">
                <h2><?php esc_html_e( 'Uploaded Fonts', 'use-custom-font' ); ?></h2>

                <?php if ( empty( $fonts ) ) : ?>
                    <p><?php esc_html_e( 'No fonts uploaded yet.', 'use-custom-font' ); ?></p>
                <?php else : ?>
                    <table class="widefat striped ucf-font-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Preview', 'use-custom-font' ); ?></th>
                                <th><?php esc_html_e( 'Font Family', 'use-custom-font' ); ?></th>
                                <th><?php esc_html_e( 'Weight', 'use-custom-font' ); ?></th>
                                <th><?php esc_html_e( 'Style', 'use-custom-font' ); ?></th>
                                <th><?php esc_html_e( 'File', 'use-custom-font' ); ?></th>
                                <th><?php esc_html_e( 'Actions', 'use-custom-font' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $fonts as $font ) : ?>
                            <tr>
                                <td>
                                    <style>
                                        @font-face {
                                            font-family: '<?php echo esc_attr( 'ucf-preview-' . $font->id ); ?>';
                                            src: url('<?php echo esc_url( $font->file_url ); ?>') format('woff2');
                                            font-weight: <?php echo esc_attr( $font->font_weight ); ?>;
                                            font-style: <?php echo esc_attr( $font->font_style ); ?>;
                                        }
                                    </style>
                                    <span style="font-family:'<?php echo esc_attr( 'ucf-preview-' . $font->id ); ?>'; font-size:18px; font-weight:<?php echo esc_attr( $font->font_weight ); ?>; font-style:<?php echo esc_attr( $font->font_style ); ?>;">
                                        The quick brown fox jumps over the lazy dog
                                    </span>
                                </td>
                                <td><?php echo esc_html( $font->font_name ); ?></td>
                                <td><?php echo esc_html( $font->font_weight ); ?></td>
                                <td><?php echo esc_html( $font->font_style ); ?></td>
                                <td><code style="font-size:12px"><?php echo esc_html( basename( $font->file_url ) ); ?></code></td>
                                <td>
                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;" onsubmit="return confirm('<?php esc_attr_e( 'Delete this font?', 'use-custom-font' ); ?>');">
                                        <?php wp_nonce_field( 'ucf_delete_font_' . $font->id, 'ucf_nonce' ); ?>
                                        <input type="hidden" name="action" value="ucf_delete_font">
                                        <input type="hidden" name="font_id" value="<?php echo esc_attr( $font->id ); ?>">
                                        <button type="submit" class="button button-link-delete"><?php esc_html_e( 'Delete', 'use-custom-font' ); ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Assign fonts to elements ----------------------------------- -->
            <?php if ( ! empty( $families ) ) : ?>
            <div class="ucf-card">
                <h2><?php esc_html_e( 'Assign Fonts to Elements', 'use-custom-font' ); ?></h2>
                <p class="description"><?php esc_html_e( 'Choose which font family to apply to common CSS elements on the frontend.', 'use-custom-font' ); ?></p>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'ucf_save_assignments', 'ucf_nonce' ); ?>
                    <input type="hidden" name="action" value="ucf_save_assignments">

                    <table class="form-table">
                        <?php
                        $elements = array(
                            'body'    => __( 'Body (default)', 'use-custom-font' ),
                            'h1'     => __( 'H1', 'use-custom-font' ),
                            'h2'     => __( 'H2', 'use-custom-font' ),
                            'h3'     => __( 'H3', 'use-custom-font' ),
                            'h4'     => __( 'H4', 'use-custom-font' ),
                            'h5'     => __( 'H5', 'use-custom-font' ),
                            'h6'     => __( 'H6', 'use-custom-font' ),
                            'p'      => __( 'Paragraphs (p)', 'use-custom-font' ),
                            'a'      => __( 'Links (a)', 'use-custom-font' ),
                            'button' => __( 'Buttons', 'use-custom-font' ),
                            'input'  => __( 'Inputs / Textareas', 'use-custom-font' ),
                            'custom' => __( 'Custom CSS Selector', 'use-custom-font' ),
                        );
                        foreach ( $elements as $key => $label ) :
                            $selected_slug = isset( $assignments[ $key ]['font'] ) ? $assignments[ $key ]['font'] : '';
                            $custom_selector = isset( $assignments[ $key ]['selector'] ) ? $assignments[ $key ]['selector'] : '';
                        ?>
                        <tr>
                            <th><?php echo esc_html( $label ); ?></th>
                            <td>
                                <select name="ucf_assign[<?php echo esc_attr( $key ); ?>][font]">
                                    <option value=""><?php esc_html_e( '— None —', 'use-custom-font' ); ?></option>
                                    <?php foreach ( $families as $slug => $name ) : ?>
                                        <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $selected_slug, $slug ); ?>><?php echo esc_html( $name ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ( 'custom' === $key ) : ?>
                                    <input type="text" name="ucf_assign[custom][selector]" value="<?php echo esc_attr( $custom_selector ); ?>" placeholder=".my-class, #my-id" class="regular-text" style="margin-left:8px;">
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>

                    <?php submit_button( __( 'Save Assignments', 'use-custom-font' ) ); ?>
                </form>
            </div>
            <?php endif; ?>

        </div><!-- .wrap -->
        <?php
    }

    /* ------------------------------------------------------------------ */
    /*  Handle font upload                                                 */
    /* ------------------------------------------------------------------ */
    public function handle_upload() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }
        check_admin_referer( 'ucf_upload_font', 'ucf_nonce' );

        $font_name   = sanitize_text_field( $_POST['ucf_font_name'] ?? '' );
        $font_weight = sanitize_text_field( $_POST['ucf_font_weight'] ?? '400' );
        $font_style  = sanitize_text_field( $_POST['ucf_font_style'] ?? 'normal' );

        if ( empty( $font_name ) || empty( $_FILES['ucf_font_file']['name'] ) ) {
            $this->redirect_with_notice( 'error', __( 'Please fill all fields and choose a file.', 'use-custom-font' ) );
        }

        $file = $_FILES['ucf_font_file'];
        $ext  = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

        if ( 'woff2' !== $ext ) {
            $this->redirect_with_notice( 'error', __( 'Only .woff2 files are allowed.', 'use-custom-font' ) );
        }

        // Use WordPress upload directory: /wp-content/uploads/ucf-fonts/
        $upload_dir  = wp_upload_dir();
        $target_dir  = $upload_dir['basedir'] . '/ucf-fonts/';
        $target_url  = $upload_dir['baseurl'] . '/ucf-fonts/';

        if ( ! file_exists( $target_dir ) ) {
            wp_mkdir_p( $target_dir );
        }

        $safe_name  = sanitize_file_name( $file['name'] );
        $safe_name  = wp_unique_filename( $target_dir, $safe_name );
        $dest_path  = $target_dir . $safe_name;
        $dest_url   = $target_url . $safe_name;

        if ( ! move_uploaded_file( $file['tmp_name'], $dest_path ) ) {
            $this->redirect_with_notice( 'error', __( 'Could not save the uploaded file.', 'use-custom-font' ) );
        }

        // Store in DB.
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'ucf_fonts',
            array(
                'font_name'   => $font_name,
                'font_slug'   => sanitize_title( $font_name ),
                'font_weight' => $font_weight,
                'font_style'  => $font_style,
                'file_url'    => $dest_url,
                'file_path'   => $dest_path,
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        $this->redirect_with_notice( 'success', __( 'Font uploaded successfully!', 'use-custom-font' ) );
    }

    /* ------------------------------------------------------------------ */
    /*  Handle font delete                                                 */
    /* ------------------------------------------------------------------ */
    public function handle_delete() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $font_id = absint( $_POST['font_id'] ?? 0 );
        check_admin_referer( 'ucf_delete_font_' . $font_id, 'ucf_nonce' );

        global $wpdb;
        $table = $wpdb->prefix . 'ucf_fonts';
        $font  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $font_id ) );

        if ( $font ) {
            // Delete file from disk.
            if ( file_exists( $font->file_path ) ) {
                wp_delete_file( $font->file_path );
            }
            $wpdb->delete( $table, array( 'id' => $font_id ), array( '%d' ) );
            $this->redirect_with_notice( 'success', __( 'Font deleted.', 'use-custom-font' ) );
        } else {
            $this->redirect_with_notice( 'error', __( 'Font not found.', 'use-custom-font' ) );
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Handle save assignments                                            */
    /* ------------------------------------------------------------------ */
    public function handle_save_assignments() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }
        check_admin_referer( 'ucf_save_assignments', 'ucf_nonce' );

        $raw = $_POST['ucf_assign'] ?? array();
        $clean = array();

        foreach ( $raw as $key => $val ) {
            $key = sanitize_key( $key );
            $clean[ $key ] = array(
                'font'     => sanitize_text_field( $val['font'] ?? '' ),
                'selector' => sanitize_text_field( $val['selector'] ?? '' ),
            );
        }

        update_option( 'ucf_font_assignments', $clean );
        $this->redirect_with_notice( 'success', __( 'Font assignments saved.', 'use-custom-font' ) );
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                            */
    /* ------------------------------------------------------------------ */
    private function redirect_with_notice( $type, $message ) {
        set_transient( 'ucf_admin_notice', array( 'type' => $type, 'message' => $message ), 30 );
        wp_safe_redirect( admin_url( 'admin.php?page=ucf-custom-fonts' ) );
        exit;
    }
}

new UCF_Admin();
