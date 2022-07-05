<?php
class ModelExtensionModuleSmartsearch extends Model {

    public function getProductSql($search = '') {

        $input_m = $search;
        $input_m = mb_strtolower($input_m);
        $input_m = trim($input_m);

        $input_m = preg_replace('/^([ ]+)|([ ]){2,}/m', '$2', $input_m);
        $input_m = $this->textswitch($input_m);

        //релевантность по полному совпадению с начала строки (максимальная релевантность)
        $sqls = array();
        $sql = "SELECT product_id, product_keyword, weight, ";
        $sql .= "(0 ";
        $sql .= "+ IF (product_keyword = '" . $this->db->escape($input_m) . "', 20*3, 0) ";
        $sql .= "+ IF (product_keyword LIKE '" . $this->db->escape($input_m) . "%', 19*3, 0) ";
        $sql .= "+ IF (product_keyword LIKE '%" . $this->db->escape($input_m) . "%', 18*3, 0) ";


        preg_match_all ( '/([0-9a-zа-яё]+)/ui', $input_m, $word_pma );
        $words = $word_pma[0];


        //релевантность по наличию каждого слова с точным совпадением
        foreach ($words as $key => $word) {
            if (mb_strlen($word) < 2) {
                continue;
            }
            $sql .= "+ IF (product_keyword LIKE '%" . $this->db->escape($word) . "%', 10, 0) ";
        }

        $sql .= ") AS relevant ";

        $sql .= "FROM " . DB_PREFIX . "search_product_index ";
        $sql .= "WHERE (product_keyword LIKE '%" . $this->db->escape($input_m) . "%' AND weight = 2) OR (product_keyword LIKE '" . $this->db->escape($input_m) . "%' AND weight = 1) ";

        for ($i = 0, $len = mb_strlen($input_m); $i < $len; $i++) {
            for ($n = 1; $n < 4; $n++) {
                $sql .= "OR (product_keyword LIKE '%" . $this->db->escape(substr($input_m, 0, $i) . ($n == 1  || $n == 3 ? '_' : '') . substr($input_m, $i + ($n > 1 ? 1 : 0))). "%' AND weight = 2) ";
            }
        }

        return $sql;
    }

    public function getCategorySql($search = '') {

        $input_m = $search;
        $input_m = mb_strtolower($input_m);
        $input_m = trim($input_m);

        $input_m = preg_replace('/^([ ]+)|([ ]){2,}/m', '$2', $input_m);
        $input_m = $this->textswitch($input_m);

        //релевантность по полному совпадению с начала строки (максимальная релевантность)
        $sqls = array();
        $sql = "SELECT category_id, parent_id, category_keyword, weight, ";
        $sql .= "(0 ";
        $sql .= "+ IF (category_keyword = '" . $this->db->escape($input_m) . "', 20*3, 0) ";
        $sql .= "+ IF (category_keyword LIKE '" . $this->db->escape($input_m) . "%', 19*3, 0) ";
        $sql .= "+ IF (category_keyword LIKE '%" . $this->db->escape($input_m) . "%', 18*3, 0) ";


        preg_match_all ( '/([0-9a-zа-яё]+)/ui', $input_m, $word_pma );
        $words = $word_pma[0];

        //релевантность по наличию каждого слова с точным совпадением
        foreach ($words as $key => $word) {
            if (mb_strlen($word) < 2) {
                continue;
            }
            $sql .= "+ IF (category_keyword LIKE '%" . $this->db->escape($word) . "%', 10, 0) ";
        }

        $sql .= ") AS relevant ";

        $sql .= "FROM " . DB_PREFIX . "search_category_index ";
        $sql .= "WHERE (category_keyword LIKE '%" . $this->db->escape($input_m) . "%' AND weight = 2) OR (category_keyword LIKE '" . $this->db->escape($input_m) . "%' AND weight = 1) ";

        for ($i = 0, $len = mb_strlen($input_m); $i < $len; $i++) {
            for ($n = 1; $n < 4; $n++) {
                $sql .= "OR (category_keyword LIKE '%" . $this->db->escape(substr($input_m, 0, $i) . ($n == 1  || $n == 3 ? '_' : '') . substr($input_m, $i + ($n > 1 ? 1 : 0))). "%' AND weight = 2) ";
            }
        }

        return $sql;
    }

    public function getProducts($data = array()) {
        $product_data = array();

        $sql = $this->getProductSql($data['filter_name']);
        
        $sql .= " GROUP BY product_id ";
        $sql .= " ORDER BY relevant DESC, product_keyword";
        $sql .= " LIMIT " . $data['limit'];
        
        $mysql = $sql;

        $query = $this->db->query($mysql);
        foreach ($query->rows as $result) {
            $product_data[$result['product_id']] = $this->getProduct($result['product_id']);
        }

        return $product_data;

    }

    public function getCategories($data = array()) {
        $category_data = array();

        $sql = $this->getCategorySql($data['filter_name']);

        $sql .= " GROUP BY category_id ";
        $sql .= " ORDER BY relevant DESC, category_keyword";
        $sql .= " LIMIT " . 3;

        $mysql = $sql;

        $query = $this->db->query($mysql);
        foreach ($query->rows as $result) {
            $category_data[$result['category_id']] = $this->getCategory($result['category_id']);
        }

        return $category_data;

    }

    public function getProductsPage($data = array()) {
        $this->load->model('catalog/product');

        $sql_search = $this->getSql($data['filter_name']);

        $sql = "SELECT p.product_id, (SELECT AVG(rating) AS total FROM " . DB_PREFIX . "review r1 WHERE r1.product_id = p.product_id AND r1.status = '1' GROUP BY r1.product_id) AS rating, (SELECT price FROM " . DB_PREFIX . "product_discount pd2 WHERE pd2.product_id = p.product_id AND pd2.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND pd2.quantity = '1' AND ((pd2.date_start = '0000-00-00' OR pd2.date_start < NOW()) AND (pd2.date_end = '0000-00-00' OR pd2.date_end > NOW())) ORDER BY pd2.priority ASC, pd2.price ASC LIMIT 1) AS discount, (SELECT price FROM " . DB_PREFIX . "product_special ps WHERE ps.product_id = p.product_id AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW())) ORDER BY ps.priority ASC, ps.price ASC LIMIT 1) AS special";

        if (!empty($data['filter_category_id'])) {
            if (!empty($data['filter_sub_category'])) {
                $sql .= " FROM " . DB_PREFIX . "category_path cp LEFT JOIN " . DB_PREFIX . "product_to_category p2c ON (cp.category_id = p2c.category_id)";
            } else {
                $sql .= " FROM " . DB_PREFIX . "product_to_category p2c";
            }

            if (!empty($data['filter_filter'])) {
                $sql .= " LEFT JOIN " . DB_PREFIX . "product_filter pf ON (p2c.product_id = pf.product_id) LEFT JOIN " . DB_PREFIX . "product p ON (pf.product_id = p.product_id)";
            } else {
                $sql .= " LEFT JOIN " . DB_PREFIX . "product p ON (p2c.product_id = p.product_id)";
            }
        } else {
            $sql .= " FROM " . DB_PREFIX . "product p";
        }

        $sql .= " INNER JOIN (" . $sql_search . ") psch ON (p.product_id = psch.product_id) ";

        $sql .= " LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE pd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'";

        if (!empty($data['filter_category_id'])) {
            if (!empty($data['filter_sub_category'])) {
                $sql .= " AND cp.path_id = '" . (int)$data['filter_category_id'] . "'";
            } else {
                $sql .= " AND p2c.category_id = '" . (int)$data['filter_category_id'] . "'";
            }

            if (!empty($data['filter_filter'])) {
                $implode = array();

                $filters = explode(',', $data['filter_filter']);

                foreach ($filters as $filter_id) {
                    $implode[] = (int)$filter_id;
                }

                $sql .= " AND pf.filter_id IN (" . implode(',', $implode) . ")";
            }
        }


        $sql .= " GROUP BY p.product_id";

        $sort_data = array(
            'pd.name',
            'p.model',
            'p.quantity',
            'p.price',
            'rating',
            'p.sort_order',
            'p.date_added'
        );

        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            if ($data['sort'] == 'pd.name' || $data['sort'] == 'p.model') {
                $sql .= " ORDER BY LCASE(" . $data['sort'] . ")";
            } elseif ($data['sort'] == 'p.price') {
                $sql .= " ORDER BY (CASE WHEN special IS NOT NULL THEN special WHEN discount IS NOT NULL THEN discount ELSE p.price END)";
            } else {
                $sql .= " ORDER BY " . $data['sort'];
            }
        } else {
            $sql .= " ORDER BY p.sort_order";
        }

        if (isset($data['order']) && ($data['order'] == 'DESC')) {
            $sql .= " DESC, LCASE(pd.name) DESC";
        } else {
            $sql .= " ASC, LCASE(pd.name) ASC";
        }

        if (isset($data['start']) || isset($data['limit'])) {
            if ($data['start'] < 0) {
                $data['start'] = 0;
            }

            if ($data['limit'] < 1) {
                $data['limit'] = 20;
            }

            $sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
        }
        //var_dump($sql);
        $product_data = array();

        $query = $this->db->query($sql);
        foreach ($query->rows as $result) {
            $product_data[$result['product_id']] = $this->model_catalog_product->getProduct($result['product_id']);
        }

        return $product_data;

    }

    public function getProductsPageTotal($data = array()) {

        $sql_search = $this->getSql($data['filter_name']);

        $sql = "SELECT COUNT(DISTINCT p.product_id) AS total";

        if (!empty($data['filter_category_id'])) {
            if (!empty($data['filter_sub_category'])) {
                $sql .= " FROM " . DB_PREFIX . "category_path cp LEFT JOIN " . DB_PREFIX . "product_to_category p2c ON (cp.category_id = p2c.category_id)";
            } else {
                $sql .= " FROM " . DB_PREFIX . "product_to_category p2c";
            }

            if (!empty($data['filter_filter'])) {
                $sql .= " LEFT JOIN " . DB_PREFIX . "product_filter pf ON (p2c.product_id = pf.product_id) LEFT JOIN " . DB_PREFIX . "product p ON (pf.product_id = p.product_id)";
            } else {
                $sql .= " LEFT JOIN " . DB_PREFIX . "product p ON (p2c.product_id = p.product_id)";
            }
        } else {
            $sql .= " FROM " . DB_PREFIX . "product p";
        }

        $sql .= " INNER JOIN (" . $sql_search . ") psch ON (p.product_id = psch.product_id) ";

        $sql .= " LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE pd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'";

        if (!empty($data['filter_category_id'])) {
            if (!empty($data['filter_sub_category'])) {
                $sql .= " AND cp.path_id = '" . (int)$data['filter_category_id'] . "'";
            } else {
                $sql .= " AND p2c.category_id = '" . (int)$data['filter_category_id'] . "'";
            }

            if (!empty($data['filter_filter'])) {
                $implode = array();

                $filters = explode(',', $data['filter_filter']);

                foreach ($filters as $filter_id) {
                    $implode[] = (int)$filter_id;
                }

                $sql .= " AND pf.filter_id IN (" . implode(',', $implode) . ")";
            }
        }

        $query = $this->db->query($sql);

        return $query->row['total'];
    }



    public function getProduct($product_id) {
        $query = $this->db->query("SELECT p.product_id, p.model, pd.name AS name, p.image, p.price, p.tax_class_id, 
			(SELECT price FROM " . DB_PREFIX . "product_discount pd2 WHERE pd2.product_id = p.product_id AND pd2.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND pd2.quantity = '1' AND ((pd2.date_start = '0000-00-00' OR pd2.date_start < NOW()) AND (pd2.date_end = '0000-00-00' OR pd2.date_end > NOW())) ORDER BY pd2.priority ASC, pd2.price ASC LIMIT 1) AS discount, 
			(SELECT price FROM " . DB_PREFIX . "product_special ps WHERE ps.product_id = p.product_id AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW())) ORDER BY ps.priority ASC, ps.price ASC LIMIT 1) AS special FROM " . DB_PREFIX . "product p 
			LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) 
			LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) 
			WHERE p.product_id = '" . (int)$product_id . "' 
			AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "' 
			AND p.status = '1' 
			AND p.date_available <= NOW() 
			AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'");

        if ($query->num_rows) {
            return array(
                'product_id'       => $query->row['product_id'],
                'product_keyword'             => $query->row['name'],
                'product_image'            => $query->row['image'],
                'price'            => ($query->row['discount'] ? $query->row['discount'] : $query->row['price']),
            );
        } else {
            return false;
        }
    }

    public function getCategory($category_id) {

        $query = $this->db->query("SELECT c.category_id, c.parent_id, cd.name, (SELECT cd2.name FROM oc_category_description cd2 WHERE cd2.category_id = c.parent_id) as category_parent_name FROM oc_category c LEFT JOIN oc_category_description cd ON c.category_id = cd.category_id WHERE c.category_id = " . (int)$category_id);

        if ($query->num_rows) {
            return array(
                'category_id'      => $query->row['category_id'],
                'parent_id'        => $query-row['parent_id'],
                'category_keyword'     => $query->row['name'],
                'category_parent_name'     => $query->row['category_parent_name'],
            );
        } else {
            return false;
        }
    }

    public function search_product_index() {

        $this->db->query("DELETE FROM " . DB_PREFIX . "search_product_index");

        $query = $this->db->query("SELECT p.product_id, pd.name FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) WHERE p.status = 1");

        foreach ($query->rows as $product) {
            $product['name'] = $this->textswitch(strip_tags(mb_strtolower($product['name'], "UTF-8" )));
            $this->db->query("INSERT IGNORE INTO " . DB_PREFIX . "search_product_index SET `product_keyword` = '" . $this->db->escape($product['name']) . "', `product_id` = " . (int)$product['product_id'] . ", weight = 2");

        }
    }

    public function search_category_index() {

        $this->db->query("DELETE FROM " . DB_PREFIX . "search_category_index");

        $query = $this->db->query("SELECT c.category_id, cd.name, c.parent_id FROM " . DB_PREFIX . "category c LEFT JOIN " . DB_PREFIX . "category_description cd ON (c.category_id = cd.category_id) WHERE c.status = 1");

        $categories = $query->rows;

        $sql = "SELECT cd.name FROM " . DB_PREFIX . "category_description cd WHERE cd.category_id = ";

        foreach ($categories as $category) {

            $category['name'] = $this->textswitch(strip_tags(mb_strtolower($category['name'], "UTF-8" )));

            $parent_name = '';

            if ($category['parent_id']) {

                $parent_name = $this->db->query($sql . (int)$category['parent_id'])->row['name'];

                $parent_name = $this->textswitch($parent_name);

            }

            $category_products = $this->db->query("SELECT pd.name FROM oc_product_description pd LEFT JOIN oc_product_to_category ptc ON pd.product_id = ptc.product_id LEFT JOIN oc_category c ON c.category_id = ptc.category_id WHERE c.parent_id != 0 AND ptc.category_id = " . (int)$category['category_id'])->rows;

            $productNames = [];

            foreach ($category_products as $product) {

                $productNames[] = $product['name'];

            }

            $productName = implode(' ', $productNames);

            $productName = $this->textswitch($productName);

            var_dump($productName);

            $this->db->query("INSERT INTO " . DB_PREFIX . "search_category_index SET `category_id` = " . (int)$category['category_id'] . ", `parent_id` = " . (int)$category['parent_id'] . ", `category_keyword` = '" . $this->db->escape($category['name']) . ' ' . $this->db->escape($productName) . "', `category_parent_name` = '" . $this->db->escape($parent_name) . "', weight = 2");

        }
    }

    public function textswitch($text) {

        $str_search = array(
            "ё","0","1","2","3","4","5","6","7","8","9",
            "й","ц","у","к","е","н","г","ш","щ","з","х","ъ",
            "ф","ы","в","а","п","р","о","л","д","ж","э",
            "я","ч","с","м","и","т","ь","б","ю","q","w","e","r","t","y","u","i","o","p",
            "a","s","d","f","g","h","j","k","l","z","x","c","v","b","n","m"
        );
        $str_replace = array(
            "t","0","1","2","3","4","5","6","7","8","9",
            "q","w","e","r","t","y","u","i","o","p","[","]",
            "a","s","d","f","g","h","j","k","l",";","'",
            "z","x","c","v","b","n","m",",",".","q","w","e","r","t","y","u","i","o","p",
            "a","s","d","f","g","h","j","k","l","z","x","c","v","b","n","m"
        );

        $text = mb_strtolower($text);

        return str_replace($str_search, $str_replace, $text);
    }

    public function textswitchRu($text) {

        $str_search = array(
            "t","0","1","2","3","4","5","6","7","8","9",
            "q","w","e","r","t","y","u","i","o","p","[","]",
            "a","s","d","f","g","h","j","k","l",";","'",
            "z","x","c","v","b","n","m",",",".","q","w","e","r","t","y","u","i","o","p",
            "a","s","d","f","g","h","j","k","l","z","x","c","v","b","n","m"
        );

        $str_replace = array(
            "ё","0","1","2","3","4","5","6","7","8","9",
            "й","ц","у","к","е","н","г","ш","щ","з","х","ъ",
            "ф","ы","в","а","п","р","о","л","д","ж","э",
            "я","ч","с","м","и","т","ь","б","ю","q","w","e","r","t","y","u","i","o","p",
            "a","s","d","f","g","h","j","k","l","z","x","c","v","b","n","m"
        );

        $text = mb_strtolower($text);

        return str_replace($str_search, $str_replace, $text);
    }

}