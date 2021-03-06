<?php

class LaterPayModelPostViews {
    /**
     * Name of PostViews table
     *
     * @var string
     *
     * @access public
     */
    public $table;

    /**
     * Constructor for class LaterPayModelPostViews, load table name
     */
    function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'laterpay_post_views';
    }

    /**
     * Get post views
     *
     * @access public
     *
     * @return array views
     */
    public function getPostViewData( $post_id ) {
        global $wpdb;

        $views = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE `post_id` = %d;"), $post_id);

        return $views;
    }

    /**
     * Save payment to payment history
     *
     * @param array $data payment data
     *
     * @access public
     */
    public function updatePostViews( $data ) {
        global $wpdb;

        $sql = "
            INSERT INTO
                {$this->table} (`post_id`, `user_id`, `date`, `ip`)
            VALUES
                ('%d', '%s', '%s', '%s')
            ON DUPLICATE KEY UPDATE
                count = count + 1
            ;
        ";

        try {
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    $sql,
                    $data['post_id'],
                    $data['user_id'],
                    date('Y-m-d H:i:s', $data['date']),
                    $data['ip'] )
            );
        } catch ( Exception $e ) {
            // nothing to do - user has already viewed page
        }
    }

    /**
     * Get last 30 days' history by post id
     *
     * @param int $post_id id post
     *
     * @access public
     *
     * @return array history
     */
    public function getLast30DaysHistory( $post_id ) {
        global $wpdb;

        $sql = "
            SELECT
                DATE(wlpv.date) AS date,
                COUNT(*) AS quantity
            FROM
                {$this->table} AS wlpv
            WHERE
                wlpv.post_id = %d
                AND wlpv.date
                    BETWEEN DATE(SUBDATE('%s', INTERVAL 30 DAY))
                    AND '%s'
            GROUP BY
                DATE(wlpv.date)
            ORDER BY
                DATE(wlpv.date) ASC
            ;
        ";

        $history = $wpdb->get_results(
            $wpdb->prepare(
                $sql,
                $post_id,
                date('Y-m-d 00:00:00'),
                date('Y-m-d 23:59:59')
            )
        );

        return $history;
    }

    /**
     * Get today's history by post id
     *
     * @param int $post_id id post
     *
     * @access public
     *
     * @return array history
     */
    public function getTodayHistory( $post_id ) {
        global $wpdb;

        $sql = "
            SELECT
                COUNT(*) AS quantity
            FROM
                {$this->table} AS wlpv
            WHERE
                wlpv.post_id = %d
                AND wlpv.date
                    BETWEEN '%s'
                    AND '%s'
            ;
        ";

        $history = $wpdb->get_results(
            $wpdb->prepare(
                $sql,
                $post_id,
                date('Y-m-d 00:00:00'),
                date('Y-m-d 23:59:59')
            )
        );

        return $history;
    }

}
