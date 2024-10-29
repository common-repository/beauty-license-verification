<?php
/**
 * Class BeautyLicenseVerification DB.
 */
class BeautyLicenseVerificationDB
{
    private $tableName = 'blv_history';

    public function __construct()
    {
        $this->tableExists();
    }

    private function query($sql)
    {
        global $wpdb;

        $res = false;
        try {
            $res = $wpdb->get_results($sql);
        } catch (Exception $e) {
            $err = $e->getMessage();
        }

        return $res;
    }

    private function tableExists()
    {
        $result = false;
        $res    = $this->query('SHOW TABLES LIKE "'.$this->tableName.'"');
        if ($res) {
            $result = true;
        } else {
            $res = $this->createTable();
            if ($res) {
                $result = true;
            }
        }

        return $result;
    }

    private function createTable()
    {
        $sql = 'CREATE TABLE `'.$this->tableName."` (
            `id` int(11) NOT NULL,
            `status` enum('login','verified','failed') COLLATE utf8mb4_general_ci NOT NULL,
            `type` enum('signup','login') COLLATE utf8mb4_general_ci NOT NULL,
            `firstname` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
            `lastname` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
            `license` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
            `state` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
            `email` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
            `date` datetime NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
        $this->query($sql);

        $sql = 'ALTER TABLE `blv_history` ADD PRIMARY KEY (`id`)';
        $this->query($sql);

        $sql = 'ALTER TABLE `blv_history` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT';
        $this->query($sql);

        return true;
    }

    public function addHistoryItem($item = [])
    {
        if (empty($item['date'])) {
            $item['date'] = \date('Y-m-d H:i:s');
        }

        $sql = 'DELETE FROM `'.$this->tableName.'` WHERE `status` = "failed" and `email` = "'.$item['email'].'"';
        $this->query($sql);

        $sql = 'INSERT INTO '.$this->tableName.
            ' ('.implode(',', array_keys($item)).') VALUES ("'.implode('","', $item).'")';

        return $this->query($sql);
    }

    public function getItems($filter = [], $period = [], $page = 1, $limit = 5)
    {
        $offset = 0;
        if (1 !== $page) {
            $offset = $limit * ($page - 1);
        }

        $statistic = ['total' => 0, 'success' => 0, 'failed' => 0];
        $result    = ['data' => [], 'periods' => []];

        $sql   = 'SELECT * from '.$this->tableName;
        $where = '';
        if ($filter) {
            foreach ($filter as $k => $v) {
                $where .= $k.'="'.$v.'"';
            }
        }

        if ($period) {
            $period = explode(' - ', $period);
            if ($where) {
                $where .= ' and ';
            }
            $where .= "date >= '".$period[0]."' and date <= '".$period[1]."'";
        }

        if ($where) {
            $sql .= ' WHERE '.$where;
        }

        $all = $this->query($sql);
        if ($all) {
            foreach ($all as $event) {
                ++$statistic['total'];
                if ('failed' == $event->status) {
                    ++$statistic['failed'];
                } else {
                    ++$statistic['success'];
                }
            }
        }

        $sql .= ' ORDER BY DATE DESC';
        $sql .= ' LIMIT '.$offset.', '.$limit;

        $res = $this->query($sql);
        if ($res) {
            $result['data'] = $res;
        }

        $sql = 'SELECT 
				concat(
					DATE(DATE_ADD(date, INTERVAL(-WEEKDAY(date)) DAY)), 
					\' - \',
					DATE(DATE_ADD(date, INTERVAL(6-WEEKDAY(date)) DAY))
				) as period FROM `blv_history` ';
        if ($where) {
            // $sql .= ' WHERE '. $where;
        }
        $sql .= ' GROUP by period';
        $res = $this->query($sql);
        if ($res) {
            $result['periods'] = $res;
        }

        $result['statistic'] = $statistic;

        return $result;
    }

    public function addTag($id)
    {
        $availableTags = [];
        $tags          = wp_get_object_terms($id, 'product_tag');
        foreach ($tags as $tag) {
            $availableTags[] = $tag->slug;
        }

        $availableTags[] = 'beauty-pro';
        wp_set_object_terms($id, $availableTags, 'product_tag');

        return true;
    }

    public function removeTag($id)
    {
        $availableTags = [];
        $tags          = wp_get_object_terms($id, 'product_tag');
        foreach ($tags as $tag) {
            $slug = $tag->slug;
            if ('beauty-pro' !== $slug) {
                $availableTags[] = $slug;
            }
        }

        wp_set_object_terms($id, $availableTags, 'product_tag');

        return true;
    }

    public function getAllProducts($search)
    {
        $args = [
            'post_type'      => 'product',
            'product_tag'    => 'beauty-pro',
            'posts_per_page' => '-1',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ];
        $loop              = new WP_Query($args);
        $products_with_tag = [];
        while ($loop->have_posts()) {
            $loop->the_post();
            global $product;

            if (!empty($search)) {
                if (preg_match('/'.$search.'/i', $product->get_name())) {
                    $products_with_tag[$product->get_id()] = $product->get_name();
                }
            } else {
                $products_with_tag[$product->get_id()] = $product->get_name();
            }
        }

        $args = [
            'post_type'      => 'product',
            'posts_per_page' => '-1',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ];
        $loop                 = new WP_Query($args);
        $products_without_tag = [];
        while ($loop->have_posts()) {
            $loop->the_post();
            global $product;

            if (!array_key_exists($product->get_id(), $products_with_tag)) {
                $products_without_tag[$product->get_name()] = [
                    'id' => $product->get_id(),
                    'url'=> wp_get_attachment_url($product->get_image_id()),
                ];
            }
        }

        $result = [
            'with'    => $products_with_tag,
            'without' => $products_without_tag,
        ];

        return $result;
    }
}
