<?php

use Journal3\Opencart\Controller;
use Journal3\Utils\Arr;

class ControllerJournal3Search extends Controller {

	public function index() {
		$search = Arr::get($this->request->get, 'search');
		$category_id = Arr::get($this->request->get, 'category_id');

		$url = '';

        $this->load->model('journal3/image');
        $this->load->model('extension/module/smartsearch');
        
        $searchRequest = $this->model_extension_module_smartsearch->textswitch($this->request->get['search']);

		if ($search) {
			$url .= '&search=' . urlencode(html_entity_decode($searchRequest, ENT_QUOTES, 'UTF-8'));
		}

		$sort = 'pd.name';
		$order = 'ASC';
		$page = 1;

		$limit = (int)$this->journal3->settings->get('searchStyleSearchAutoSuggestLimit');

		if (!$limit) {
			$limit = 10;
		}

		$filter_data = array(
			'filter_name'        => $search,
			'sort'               => $sort,
			'order'              => $order,
			'start'              => 0,
			'limit'              => $limit,
		);

		if ($category_id) {
			$filter_data['filter_category_id'] = $category_id;
		}

		$resultsCategory = $this->model_extension_module_smartsearch->getCategories($filter_data);
		$resultsProduct = $this->model_extension_module_smartsearch->getProducts($filter_data);

        $results = array_merge($resultsCategory, $resultsProduct);

        $data = array();

		foreach ($results as $result) {

			if ($result['product_image']) {
				$image = $this->model_journal3_image->resize($result['product_image'], 80, 80);
			} else {
				$image = $this->model_journal3_image->resize('placeholder.png', 80, 80);
			}

			$price = false;
			$special = false;

			if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
				$price = $this->currency->format($this->tax->calculate($result['price'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);

				if ((float)$result['special']) {
					$special = $this->currency->format($this->tax->calculate($result['special'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
				}
			}

            $data[] = array(
				'product_quantity'    => (int)$result['quantity'],
				'product_price_value' => $result['special'] ? $result['special'] > 0 : $result['price'] > 0,
				'product_id'  => $result['product_id'],
				'product_name'        => html_entity_decode($result['product_keyword'], ENT_QUOTES, 'UTF-8'),
				'product_thumb'       => $image,
				'product_price'       => $price,
				'product_special'     => $special,
				'product_href'        => $this->url->link('product/product', '&product_id=' . $result['product_id']),

				'category_id'  => $result['category_id'],
				'category_name'        => html_entity_decode($result['category_keyword'], ENT_QUOTES, 'UTF-8'),
				'category_parent_name'        => html_entity_decode($result['category_parent_name'], ENT_QUOTES, 'UTF-8'),
				'category_href'        => $this->url->link('product/category', 'path=' . $result['category_id']),
			);
		}

		if ($data) {
			$url = '';

			if (isset($searchRequest)) {
				$url .= '&search=' . urlencode(html_entity_decode($searchRequest, ENT_QUOTES, 'UTF-8'));
			}

//			if ($this->journal3->settings->get('searchStyleSearchAutoSuggestDescription')) {
//				$url .= '&description=true';
//			}

			if (isset($this->request->get['category_id'])) {
				$url .= '&category_id=' . $this->request->get['category_id'];
			}

			if (isset($this->request->get['sub_category'])) {
				$url .= '&sub_category=' . $this->request->get['sub_category'];
			}

            $data[] = array(
				'view_more' => true,
				'view_more_name'      => $this->journal3->settings->get('searchStyleSearchViewMoreText'),
				'view_more_href'      => $this->url->link('product/search', $url),
			);
		} else {
            $data[] = array(
				'no_results' => true,
				'no_results_name'       => $this->journal3->settings->get('searchStyleSearchNoResultsText'),
			);
		}

		$this->renderJson('success', $data);
	}

}