<?php

class PrismSearchAreaWebhook extends PrismappIoWebhook implements iPrismappIoWebhook {

    const MIN_CHAR = 3;

    private $request_data;
    private $response_data;
    private $provinsi_data;

    /**
     * Bootstrapper for the controller
     * ---
     */
    public function run($request_data) {
        $this->request_data = $request_data;
        $this->validate_request();

        $this->process();
        $this->validate_response();

        PrismappIoWebhook::response_with_json(json_encode($this->response_data), 200);
    }

    /**
     * Do Webhook Logic
     * ---
     */
    public function process() {
        global $woocommerce;

        if ( version_compare( $woocommerce->version, '2.2', "<" ) ) {
            PrismappIoWebhook::response_woo_need_upgrade();
        }
        
        $keyword = trim(sanitize_text_field($this->request_data->get_param('name')));

        if(strlen($keyword) < self::MIN_CHAR){
            $this->emptyPayload();
        }

        $path = ABSPATH . PLUGINDIR .'/plugin-ongkos-kirim/plugin-ongkos-kirim.php';
        if (!file_exists($path)) {
            $this->emptyPayload();
        }
        else {
            $results = array();
            $api = new \PluginOngkosKirim\Controller\Core;
            $helper = new \PluginOngkosKirim\Helpers\Helpers();

            if(!$helper->is_plugin_active()){
                $this->emptyPayload();
            }
            else{
                if($helper->is_rajaongkir_active()){
                    $cities = $api->getAllCity();
                    if(is_array($cities)){
                        foreach($cities as $city){
                            $find = strpos(strtolower($city->city_name), strtolower($keyword));
                            if($find !== false){
                                if(get_option('nusantara_raja_ongkir_type') == 'pro'){
                                    $districts = $api->getDistrict($city->city_id);
                                    if(is_array($districts)){
                                        foreach($districts as $district){
                                            $results[] = $this->buildRajaOngkirProPayload($district);
                                        }
                                        break;
                                    }
                                }
                                else{
                                    $results[] = $this->buildRajaOngkirPayload($city);
                                    break;
                                }
                            }
                        }
                    }
                }
                else{
                    $this->provinsi_data = $api->getProvince();
                    $cities = $api->searchCity($keyword);
                    if(is_array($cities)){
                        foreach($cities as $city){
                            $districts = $api->getDistrict($city->id);
                            if(is_array($districts)){
                                foreach($districts as $district){
                                    $results[] = $this->buildAreaPayload($city, $district);
                                }
                            }
                        }
                    }
                }

                $this->response_data = array(
                    'status'    => 'success',
                    'data'      => array(
                        'results'    => $results
                    )
                );
            }
        }
    }

    private function emptyPayload(){
        return $this->response_data = array(
            'status'    => 'success',
            'data'      => array(
                'results' => array(),
            )
        );
    }

    private function buildRajaOngkirProPayload($district) {
        $item = array(
            'label'    => $district->nama.' '.$district->type.' '.$district->city.' '.$district->province,
            'provider' => 'custom',
            'custom'   => array(
                'district_id' => $district->id,
                'city_id' => $district->city_id,
                'province_id' => $district->province_id,
                'district' => $district->subdistrict_name,
                'city' => $district->type.' '.$district->city,
                'province' => $district->province
            )
        );
        return $item;
    }

    private function buildRajaOngkirPayload($city) {
        $item = array(
            'label'    => $city->type.' '.$city->city_name.' '.$city->province,
            'provider' => 'custom',
            'custom'   => array(
                'district_id' => 0,
                'city_id' => $city->city_id,
                'province_id' => $city->province_id,
                'district' => '',
                'city' => $city->type.' '.$city->city_name,
                'province' => $city->province
            )
        );
        return $item;
    }

    private function buildAreaPayload($city, $district) {
        $item = array(
            'label'    => $district->nama.' '.$city->type.' '.$city->nama.' '.$city->provinsi,
            'provider' => 'custom',
            'custom'   => array(
                'district_id' => $district->id,
                'city_id' => $city->id,
                'province_id' => $this->getProvinceId($city->provinsi),
                'district' => $district->nama,
                'city' => $city->type.' '.$city->nama,
                'province' => $city->provinsi
            )
        );
        return $item;
    }

    private function getProvinceId($provinsi){
        $id = 0;
        foreach($this->provinsi_data as $prov){
            if($provinsi == $prov->nama){
                $id = $prov->id;
                break;
            }
        }
        return $id;
    }

    /**
     * Validate requests
     * ---
     */
    public function validate_request() {
        $area_name = $this->request_data->get_param('name');
        if(empty($area_name))
            PrismappIoWebhook::response_with_bad_request('query name is required');
    }

    /**
     * Validate responses
     * ---
     */
    public function validate_response() {
        if(empty($this->response_data))
            PrismappIoWebhook::response_with_server_error();

        if(empty($this->response_data['status']))
            PrismappIoWebhook::response_with_server_error();

        if(empty($this->response_data['data']))
            PrismappIoWebhook::response_with_server_error();
    }
}
