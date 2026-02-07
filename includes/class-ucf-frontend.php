<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Frontend: output @font-face declarations and element assignments as inline CSS.
 */
class UCF_Frontend {

    public function __construct() {
        add_action( 'wp_head', array( $this, 'print_font_css' ), 5 );
    }

    /**
     * Build and print all the CSS needed on the frontend.
     */
    public function print_font_css() {
        global $wpdb;
        $table = $wpdb->prefix . 'ucf_fonts';
        $fonts = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY font_name ASC" );

        if ( empty( $fonts ) ) {
            return;
        }

        $assignments = get_option( 'ucf_font_assignments', array() );

        // Group fonts by slug so we can emit one @font-face block per variant.
        $font_map = array(); // slug => font_name
        foreach ( $fonts as $f ) {
            $font_map[ $f->font_slug ] = $f->font_name;
        }

        $css = "\n<style id=\"ucf-custom-fonts\">\n";

        // 1. @font-face declarations.
        foreach ( $fonts as $f ) {
            $font_url = $this->maybe_force_https( $f->file_url );
            $css .= "@font-face {\n";
            $css .= "  font-family: '{$f->font_name}';\n";
            $css .= "  src: url('" . esc_url( $font_url ) . "') format('woff2');\n";
            $css .= "  font-weight: {$f->font_weight};\n";
            $css .= "  font-style: {$f->font_style};\n";
            $css .= "  font-display: swap;\n";
            $css .= "}\n";
        }

        // 2. Element selectors.
        $selector_map = array(
            'body'   => 'body',
            'h1'     => 'h1',
            'h2'     => 'h2',
            'h3'     => 'h3',
            'h4'     => 'h4',
            'h5'     => 'h5',
            'h6'     => 'h6',
            'p'      => 'p',
            'a'      => 'a',
            'button' => 'button, .button, input[type="submit"], input[type="button"]',
            'input'  => 'input, textarea, select',
        );

        foreach ( $assignments as $key => $data ) {
            $slug = $data['font'] ?? '';
            if ( empty( $slug ) || ! isset( $font_map[ $slug ] ) ) {
                continue;
            }
            $family = $font_map[ $slug ];

            if ( 'custom' === $key ) {
                $selector = sanitize_text_field( $data['selector'] ?? '' );
                if ( empty( $selector ) ) {
                    continue;
                }
            } else {
                $selector = $selector_map[ $key ] ?? '';
                if ( empty( $selector ) ) {
                    continue;
                }
            }

            $css .= "{$selector} {\n";
            $css .= "  font-family: '{$family}', sans-serif;\n";
            $css .= "}\n";
        }

        $css .= "</style>\n";

        echo $css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Force HTTPS in font URL if the option is enabled.
     */
    private function maybe_force_https( $url ) {
        if ( get_option( 'ucf_force_https' ) && strpos( $url, 'http://' ) === 0 ) {
            $url = 'https://' . substr( $url, 7 );
        }
        return $url;
    }
}

new UCF_Frontend();
