<?php
/**
 * Content Indexer
 *
 * Reads published posts/pages and stores a lightweight searchable index
 * in wp_options. This keeps things simple for Phase 1 — no external DB needed.
 *
 * @package WP_SmartChat
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPSC_Content_Indexer {

    /**
     * Build (or rebuild) the content index from published posts and pages.
     *
     * Each entry: [ 'id' => int, 'title' => str, 'url' => str, 'content' => str, 'keywords' => [] ]
     */
    public function build_index() {
        $post_types = get_option( 'wpsc_post_types', array( 'page', 'post' ) );

        $query = new WP_Query( array(
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => 200, // Reasonable limit for Phase 1
            'no_found_rows'  => true,
        ) );

        $index = array();

        foreach ( $query->posts as $post ) {
            // Strip shortcodes, HTML, extra whitespace
            $clean_content = $this->clean_text( $post->post_content );
            $clean_title   = $this->clean_text( $post->post_title );

            // Extract keywords (simple approach — top frequency words)
            $keywords = $this->extract_keywords( $clean_title . ' ' . $clean_content );

            $index[] = array(
                'id'       => $post->ID,
                'title'    => $post->post_title,
                'url'      => get_permalink( $post->ID ),
                'content'  => $this->truncate( $clean_content, 1500 ),
                'keywords' => $keywords,
                'type'     => $post->post_type,
            );
        }

        update_option( 'wpsc_content_index', $index, false ); // autoload = false (can be large)

        return $index;
    }

    /**
     * Get the stored index.
     */
    public function get_index() {
        $index = get_option( 'wpsc_content_index', array() );

        // Auto-build if empty
        if ( empty( $index ) ) {
            $index = $this->build_index();
        }

        return $index;
    }

    /**
     * Strip HTML, shortcodes, and normalize whitespace.
     */
    private function clean_text( $text ) {
        $text = wp_strip_all_tags( strip_shortcodes( $text ) );
        $text = preg_replace( '/\s+/', ' ', $text );
        return trim( $text );
    }

    /**
     * Extract the most meaningful keywords from text.
     */
    private function extract_keywords( $text, $limit = 20 ) {
        $text  = strtolower( $text );
        $words = str_word_count( $text, 1 );

        // Common stop words to ignore
        $stop_words = array(
            'the','be','to','of','and','a','in','that','have','i','it','for',
            'not','on','with','he','as','you','do','at','this','but','his',
            'by','from','they','we','say','her','she','or','an','will','my',
            'one','all','would','there','their','what','so','up','out','if',
            'about','who','get','which','go','me','when','make','can','like',
            'time','no','just','him','know','take','people','into','year',
            'your','good','some','could','them','see','other','than','then',
            'now','look','only','come','its','over','think','also','back',
            'after','use','two','how','our','work','first','well','way',
            'even','new','want','because','any','these','give','day','most',
            'us','is','are','was','were','been','being','has','had','did',
            'does','am','more','very','much',
        );

        // Count word frequency, excluding stop words and short words
        $counts = array();
        foreach ( $words as $word ) {
            if ( strlen( $word ) > 2 && ! in_array( $word, $stop_words, true ) ) {
                $counts[ $word ] = isset( $counts[ $word ] ) ? $counts[ $word ] + 1 : 1;
            }
        }

        arsort( $counts );
        return array_slice( array_keys( $counts ), 0, $limit );
    }

    /**
     * Truncate text to a max character count without breaking words.
     */
    private function truncate( $text, $max ) {
        if ( strlen( $text ) <= $max ) {
            return $text;
        }
        $text = substr( $text, 0, $max );
        return substr( $text, 0, strrpos( $text, ' ' ) ) . '…';
    }
}
