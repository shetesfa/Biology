<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Promptor_Changelog_Page {

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $full = $this->read_full_changelog_from_readme();

        echo '<div class="wrap promptor-changelog-wrap">';
        echo '<div class="promptor-page-header">';
        echo '<div class="header-content">';
        echo '<h1><span class="dashicons dashicons-backup"></span> ' . esc_html__( 'Changelog', 'promptor' ) . '</h1>';
        echo '<p class="page-subtitle">' . esc_html__( 'Track all updates, improvements, and new features added to Promptor.', 'promptor' ) . '</p>';
        echo '</div>';
        echo '</div>';
        echo '<p><a class="button button-secondary" href="' . esc_url( admin_url( 'admin.php?page=promptor' ) ) . '">&larr; ' . esc_html__( 'Back to Welcome', 'promptor' ) . '</a></p>';
        echo '<div class="postbox"><div class="inside">';
        if ( $full ) {
            $html = $this->render_changelog_html( $full );

            // Whitelist allowed tags and escape everything else
            $allowed = array(
                'h2' => array(),
                'ul' => array(),
                'li' => array(),
                'p'  => array(),
            );
            echo wp_kses( $html, $allowed );
        } else {
            echo '<p>' . esc_html__( 'Changelog could not be loaded. Please check the readme.txt file.', 'promptor' ) . '</p>';
        }
        echo '</div></div>';
        echo '</div>';
    }

    private function read_full_changelog_from_readme() {
        // Use transient cache to avoid repeated disk reads
        $cached = get_transient( 'promptor_full_changelog' );
        if ( false !== $cached && is_string( $cached ) && $cached !== '' ) {
            return $cached;
        }

        $readme = trailingslashit( PROMPTOR_PATH ) . 'readme.txt';
        if ( ! file_exists( $readme ) || ! is_readable( $readme ) ) {
            return '';
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $content = file_get_contents( $readme );
        if ( false === $content || ! is_string( $content ) || '' === $content ) {
            return '';
        }
        $start = strpos( $content, '== Changelog ==' );
        if ( false === $start ) {
            return '';
        }
        $end = strpos( $content, '== Upgrade Notice ==' );
        $section = ( false !== $end ) ? substr( $content, $start, $end - $start ) : substr( $content, $start );

        // strip the heading line
        $section = preg_replace( '/^==\s*Changelog\s*==\s*/', '', $section );
        $section = trim( $section );

        // Cache for 12 hours
        set_transient( 'promptor_full_changelog', $section, 12 * HOUR_IN_SECONDS );

        return $section;
    }

    private function render_changelog_html( $raw ) {
        $lines = preg_split( "/\r\n|\n|\r/", $raw );
        $html  = '';
        $in_ul = false;

        foreach ( $lines as $line ) {
            $line = rtrim( $line );

            if ( preg_match( '/^=\s*(.+?)\s*=/', $line, $m ) ) {
                if ( $in_ul ) { $html .= '</ul>'; $in_ul = false; }
                $version = sanitize_text_field( $m[1] );
                $html   .= '<h2>' . esc_html( $version ) . '</h2>';
                continue;
            }

            if ( preg_match( '/^\*\s+(.+)$/', $line, $m ) ) {
                if ( ! $in_ul ) { $html .= '<ul>'; $in_ul = true; }
                $item = $m[1];
                $html .= '<li>' . esc_html( $item ) . '</li>';
                continue;
            }

            if ( '' === trim( $line ) ) {
                if ( $in_ul ) { $html .= '</ul>'; $in_ul = false; }
                continue;
            }

            if ( $in_ul ) { $html .= '</ul>'; $in_ul = false; }
            $html .= '<p>' . esc_html( $line ) . '</p>';
        }
        if ( $in_ul ) { $html .= '</ul>'; }

        return $html;
    }
}