<?php

namespace GioCompress;

if ( ! defined( 'ABSPATH' ) ) exit;

class DB {

    const SOURCE_UPLOAD = 'upload';
    const SOURCE_RETRO  = 'retro';

    private $table_name;
    private $table_fields = [
        'attachment_id'     => '%d',
        'original_file'     => '%s',
        'original_size'     => '%d',
        'optimized_size'    => '%d',
        'saved_size'        => '%d',
        'status'            => '%s',
        'source'            => '%s',
        'optimization_date' => '%s',
    ];
    private $db_version_option_key = 'giocompress_db_version';

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'giocompress_optimizations';
    }

    /**
     * The activation hook does not run on plugin updates: the schema is upgraded here.
     */
    public function maybe_upgrade() {
        if ( get_option( $this->db_version_option_key ) !== GIOCOMPRESS_VERSION ) {
            $this->create_table();
        }
    }

    public function create_table() {
        global $wpdb;

        if ( ! function_exists( 'dbDelta' ) ) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            attachment_id bigint(20) NOT NULL,
            original_file varchar(255),
            original_size bigint(20) NOT NULL,
            optimized_size bigint(20) NOT NULL,
            saved_size bigint(20) NOT NULL,
            optimization_date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            status varchar(20) DEFAULT 'success' NOT NULL,
            source varchar(20) DEFAULT 'upload' NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY attachment_id (attachment_id),
            KEY optimization_date (optimization_date)
        ) $charset_collate;";

        dbDelta( $sql );

        update_option( $this->db_version_option_key, GIOCOMPRESS_VERSION );
    }

    public function get_data( int $attachment_id ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM %i WHERE attachment_id = %d",
                $this->table_name,
                $attachment_id
            )
        );
    }

    public function add_meta( int $attachment_id, array $data ) {
        global $wpdb;

        $valid_data = array_intersect_key( $data, $this->table_fields );
        unset( $valid_data['attachment_id'] );

        if ( empty( $valid_data ) ) {
            return false;
        }

        $existing = $this->get_data( $attachment_id );

        if ( !$existing ) {
            $insert_data = array_merge([
                'attachment_id'     => $attachment_id,
                'original_file'     => '',
                'original_size'     => 0,
                'optimized_size'    => 0,
                'saved_size'        => 0,
                'optimization_date' => current_time('mysql'),
                'status'            => 'completed',
                'source'            => self::SOURCE_UPLOAD,
            ], $valid_data);

            $original_size  = (int) $insert_data['original_size'];
            $optimized_size = (int) $insert_data['optimized_size'];
            $insert_data['saved_size'] = ($original_size > 0 && $optimized_size > 0) ? $original_size - $optimized_size : 0;

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->insert( $this->table_name, $insert_data, $this->formats_for( $insert_data ) );

            return $this->get_data( $attachment_id );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->update(
            $this->table_name,
            $valid_data,
            [ 'attachment_id' => $attachment_id ],
            $this->formats_for( $valid_data ),
            [ '%d' ]
        );

        // Ricalcola saved_size se uno dei due valori è stato aggiornato
        if ( isset( $valid_data['original_size'] ) || isset( $valid_data['optimized_size'] ) ) {
            $row = $this->get_data( $attachment_id );
            $original_size  = (int) $row->original_size;
            $optimized_size = (int) $row->optimized_size;

            if ( $original_size > 0 && $optimized_size > 0 ) {
                $saved_size = $original_size - $optimized_size;

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $wpdb->update(
                    $this->table_name,
                    [ 'saved_size' => $saved_size ],
                    [ 'attachment_id' => $attachment_id ],
                    [ '%d' ],
                    [ '%d' ]
                );
            }
        }

        return $this->get_data( $attachment_id );
    }

    private function formats_for( array $data ): array {
        $formats = [];
        foreach ( array_keys( $data ) as $key ) {
            $formats[] = $this->table_fields[ $key ] ?? '%s';
        }
        return $formats;
    }

    public function delete_meta( int $attachment_id ): bool {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return (bool) $wpdb->delete(
            $this->table_name,
            [ 'attachment_id' => $attachment_id ],
            [ '%d' ]
        );
    }

    /**
     * JPG/PNG attachments without an optimization record.
     */
    public function get_not_optimized_attachments($per_page = -1, $offset = -1) {
        global $wpdb;

        if ($per_page <= 0) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT p.ID FROM %i p
                    LEFT JOIN %i g ON p.ID = g.attachment_id
                    WHERE p.post_type = 'attachment'
                    AND p.post_mime_type IN ('image/jpeg', 'image/png')
                    AND g.attachment_id IS NULL
                    ORDER BY p.ID DESC",
                    $wpdb->posts,
                    $this->table_name
                )
            );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.ID FROM %i p
                LEFT JOIN %i g ON p.ID = g.attachment_id
                WHERE p.post_type = 'attachment'
                AND p.post_mime_type IN ('image/jpeg', 'image/png')
                AND g.attachment_id IS NULL
                ORDER BY p.ID DESC
                LIMIT %d OFFSET %d",
                $wpdb->posts,
                $this->table_name,
                $per_page,
                max(0, $offset)
            )
        );
    }

    public function count_not_optimized_attachments(): int {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM %i p
                LEFT JOIN %i g ON p.ID = g.attachment_id
                WHERE p.post_type = 'attachment'
                AND p.post_mime_type IN ('image/jpeg', 'image/png')
                AND g.attachment_id IS NULL",
                $wpdb->posts,
                $this->table_name
            )
        );
    }

    /**
     * Existing images optimized today. Images converted on upload do not count toward the daily limit.
     */
    public function daily_optimizations(): int {
        global $wpdb;
        $today    = current_time('Y-m-d') . ' 00:00:00';
        $tomorrow = gmdate('Y-m-d', strtotime(current_time('Y-m-d') . ' +1 day')) . ' 00:00:00';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(id) FROM %i
                WHERE source = %s AND optimization_date >= %s AND optimization_date < %s",
                $this->table_name,
                self::SOURCE_RETRO,
                $today,
                $tomorrow
            )
        );
    }

}
