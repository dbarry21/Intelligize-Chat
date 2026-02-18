<?php
/**
 * Chat Engine
 *
 * Phase 1: Keyword-matching against the content index.
 * Phase 2: Sends context + question to AI API for natural answers.
 *
 * @package WP_SmartChat
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPSC_Chat_Engine {

    private $indexer;

    public function __construct() {
        $this->indexer = new WPSC_Content_Indexer();
    }

    /**
     * Main entry: get an answer for a user message.
     *
     * @param string $message   The visitor's question.
     * @param array  $history   Previous messages in the conversation (optional).
     * @return array            [ 'answer' => string, 'sources' => [] ]
     */
    public function get_response( $message, $history = array() ) {
        $provider = get_option( 'wpsc_ai_provider', 'local' );

        switch ( $provider ) {
            case 'openai':
                return $this->get_ai_response( $message, $history, 'openai' );
            case 'anthropic':
                return $this->get_ai_response( $message, $history, 'anthropic' );
            default:
                return $this->get_local_response( $message );
        }
    }

    /**
     * ── LOCAL MODE ───────────────────────────────────────────────────────────
     * Simple keyword matching with relevance scoring.
     */
    private function get_local_response( $message ) {
        $index    = $this->indexer->get_index();
        $keywords = $this->extract_query_keywords( $message );

        if ( empty( $keywords ) ) {
            return array(
                'answer'  => $this->get_fallback_message(),
                'sources' => array(),
            );
        }

        // Score each indexed page
        $scored = array();
        foreach ( $index as $entry ) {
            $score = $this->score_match( $keywords, $entry );
            if ( $score > 0 ) {
                $scored[] = array(
                    'entry' => $entry,
                    'score' => $score,
                );
            }
        }

        // Sort by score descending
        usort( $scored, function ( $a, $b ) {
            return $b['score'] <=> $a['score'];
        } );

        if ( empty( $scored ) ) {
            return array(
                'answer'  => $this->get_fallback_message(),
                'sources' => array(),
            );
        }

        // Build a human-readable answer from the best match
        $best   = $scored[0]['entry'];
        $answer = $this->build_local_answer( $message, $best, $keywords );

        // Collect top sources
        $sources = array();
        foreach ( array_slice( $scored, 0, 3 ) as $item ) {
            $sources[] = array(
                'title' => $item['entry']['title'],
                'url'   => $item['entry']['url'],
            );
        }

        return array(
            'answer'  => $answer,
            'sources' => $sources,
        );
    }

    /**
     * Score how well an index entry matches the query keywords.
     */
    private function score_match( $query_keywords, $entry ) {
        $score          = 0;
        $title_lower    = strtolower( $entry['title'] );
        $content_lower  = strtolower( $entry['content'] );
        $entry_keywords = $entry['keywords'];

        foreach ( $query_keywords as $qk ) {
            // Title match is worth more
            if ( strpos( $title_lower, $qk ) !== false ) {
                $score += 5;
            }
            // Content match
            if ( strpos( $content_lower, $qk ) !== false ) {
                $score += 2;
            }
            // Keyword list match
            if ( in_array( $qk, $entry_keywords, true ) ) {
                $score += 3;
            }
        }

        return $score;
    }

    /**
     * Build a friendly local answer by extracting relevant sentences.
     */
    private function build_local_answer( $message, $entry, $keywords ) {
        $sentences = preg_split( '/(?<=[.!?])\s+/', $entry['content'] );
        $relevant  = array();

        foreach ( $sentences as $sentence ) {
            $lower = strtolower( $sentence );
            foreach ( $keywords as $kw ) {
                if ( strpos( $lower, $kw ) !== false ) {
                    $relevant[] = $sentence;
                    break;
                }
            }
            if ( count( $relevant ) >= 3 ) {
                break;
            }
        }

        if ( ! empty( $relevant ) ) {
            $excerpt = implode( ' ', $relevant );
        } else {
            // Fallback to first ~300 chars of content
            $excerpt = substr( $entry['content'], 0, 300 );
            if ( strlen( $entry['content'] ) > 300 ) {
                $excerpt .= '…';
            }
        }

        $answer  = "Based on our **{$entry['title']}** page:\n\n";
        $answer .= $excerpt . "\n\n";
        $answer .= "📄 [Read more]({$entry['url']})";

        return $answer;
    }

    /**
     * ── AI MODE ──────────────────────────────────────────────────────────────
     * Sends the user's question + relevant site content to an AI API.
     */
    private function get_ai_response( $message, $history, $provider ) {
        $api_key = get_option( 'wpsc_api_key', '' );

        if ( empty( $api_key ) ) {
            // Fall back to local if no key configured
            return $this->get_local_response( $message );
        }

        // Find the 3 most relevant pages to use as context
        $index    = $this->indexer->get_index();
        $keywords = $this->extract_query_keywords( $message );
        $context  = $this->get_top_context( $keywords, $index, 3 );

        $site_name = get_bloginfo( 'name' );
        $bot_name  = get_option( 'wpsc_bot_name', 'Intelligize Assistant' );

        $system_prompt = "You are \"{$bot_name}\", a helpful assistant for the website \"{$site_name}\". "
            . "Answer the visitor's question using ONLY the website content provided below. "
            . "If the answer isn't in the provided content, say you don't have that information and suggest they contact the site directly. "
            . "Be friendly, concise, and helpful. Use markdown for formatting when useful.\n\n"
            . "── WEBSITE CONTENT ──\n" . $context;

        if ( 'openai' === $provider ) {
            return $this->call_openai( $system_prompt, $message, $history, $api_key );
        } else {
            return $this->call_anthropic( $system_prompt, $message, $history, $api_key );
        }
    }

    /**
     * Call OpenAI Chat Completions API.
     */
    private function call_openai( $system, $message, $history, $api_key ) {
        $messages = array(
            array( 'role' => 'system', 'content' => $system ),
        );

        // Add conversation history
        foreach ( $history as $msg ) {
            $messages[] = array(
                'role'    => $msg['role'] === 'user' ? 'user' : 'assistant',
                'content' => sanitize_text_field( $msg['content'] ),
            );
        }

        $messages[] = array( 'role' => 'user', 'content' => sanitize_text_field( $message ) );

        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'model'       => 'gpt-4o-mini',
                'messages'    => $messages,
                'max_tokens'  => 500,
                'temperature' => 0.7,
            ) ),
        ) );

        if ( is_wp_error( $response ) ) {
            return $this->get_local_response( $message );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! empty( $body['choices'][0]['message']['content'] ) ) {
            return array(
                'answer'  => $body['choices'][0]['message']['content'],
                'sources' => array(),
            );
        }

        return $this->get_local_response( $message );
    }

    /**
     * Call Anthropic Messages API.
     */
    private function call_anthropic( $system, $message, $history, $api_key ) {
        $messages = array();

        foreach ( $history as $msg ) {
            $messages[] = array(
                'role'    => $msg['role'] === 'user' ? 'user' : 'assistant',
                'content' => sanitize_text_field( $msg['content'] ),
            );
        }

        $messages[] = array( 'role' => 'user', 'content' => sanitize_text_field( $message ) );

        $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
            'timeout' => 30,
            'headers' => array(
                'x-api-key'         => $api_key,
                'anthropic-version' => '2023-06-01',
                'Content-Type'      => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'model'      => 'claude-sonnet-4-5-20250929',
                'system'     => $system,
                'messages'   => $messages,
                'max_tokens' => 500,
            ) ),
        ) );

        if ( is_wp_error( $response ) ) {
            return $this->get_local_response( $message );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! empty( $body['content'][0]['text'] ) ) {
            return array(
                'answer'  => $body['content'][0]['text'],
                'sources' => array(),
            );
        }

        return $this->get_local_response( $message );
    }

    /**
     * Get the top N content entries as formatted context text.
     */
    private function get_top_context( $keywords, $index, $limit = 3 ) {
        $scored = array();
        foreach ( $index as $entry ) {
            $score = $this->score_match( $keywords, $entry );
            if ( $score > 0 ) {
                $scored[] = array( 'entry' => $entry, 'score' => $score );
            }
        }
        usort( $scored, function ( $a, $b ) {
            return $b['score'] <=> $a['score'];
        } );

        $context = '';
        foreach ( array_slice( $scored, 0, $limit ) as $item ) {
            $e = $item['entry'];
            $context .= "Page: {$e['title']}\nURL: {$e['url']}\nContent: {$e['content']}\n\n";
        }

        if ( empty( $context ) ) {
            // If no match, send the first few pages as general context
            foreach ( array_slice( $index, 0, $limit ) as $e ) {
                $context .= "Page: {$e['title']}\nURL: {$e['url']}\nContent: {$e['content']}\n\n";
            }
        }

        return $context;
    }

    /**
     * Extract meaningful keywords from the user's question.
     */
    private function extract_query_keywords( $message ) {
        $message = strtolower( $message );
        $words   = str_word_count( $message, 1 );

        $stop = array(
            'what','where','when','how','why','who','which','is','are','was',
            'were','do','does','did','can','could','would','should','will',
            'the','a','an','of','in','to','for','on','at','by','with','and',
            'or','but','not','this','that','these','those','i','me','my',
            'you','your','we','our','they','their','it','its','hi','hello',
            'hey','please','thanks','thank','tell','about','some','any',
            'have','has','had','get','got','much','many','more',
        );

        $keywords = array();
        foreach ( $words as $word ) {
            if ( strlen( $word ) > 2 && ! in_array( $word, $stop, true ) ) {
                $keywords[] = $word;
            }
        }

        return array_unique( $keywords );
    }

    /**
     * Friendly fallback when no match is found.
     */
    private function get_fallback_message() {
        $messages = array(
            "I'm not sure I have information about that. Could you rephrase your question, or ask about something specific on our site?",
            "I couldn't find a clear answer for that on our website. Try asking about our services, products, or any specific page topic!",
            "Hmm, I don't have a great answer for that one. You can also reach out to us directly through our contact page for more help.",
        );
        return $messages[ array_rand( $messages ) ];
    }
}
