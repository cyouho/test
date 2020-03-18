<?php
class elasticSearchLoadTest {
    private function readRequestList($file)
    {
        if (! is_file($file)) {
            exit('No Files Exist!');
        }

        $handle = fopen($file, 'r');

        if (! $handle) {
            exit('Read Files Fail!');
        }

        $requestsList = [];

        while (($data = fgetcsv($handle)) !== false) {
            $requestsList = array_merge($requestsList, $data);
        }

        return $requestsList;
    }

    private function postTestRequest($requestsList, $sleepTime, $options = '')
    {
        if (count($requestsList) <= 0) {
            exit('Requestslist Error!');
        }

        $handles = [];

        $cookies = 'PHPSESSID=n06fnop0venkuu7101j34uc2i3; _disfa=dfcac6da1b04c4f8ff4aa94a0c4d0e10; _session_id=3d33f5d22c09d1beTIME_COOKIE=1; _fbp=fb.1.1584426547659.1304210976';
        $headers = [
            'X-Requested-With: XMLHttpRequest'
        ];

        if (! $options) {
            $options = [
                CURLOPT_HEADER => 0,
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_COOKIE => $cookies,
                CURLOPT_HTTPHEADER => $headers,
            ];
        }

        foreach($requestsList as $key => $row) {
            $ch{$key} = curl_init();
            $options[CURLOPT_URL] = $row;
            $opt = curl_setopt_array($ch{$key}, $options);
            $handles[$key] = $ch{$key};
        }

        $mh = curl_multi_init();

        foreach($handles as $key => $handle) {
            $err = curl_multi_add_handle($mh, $handle);
        }

        $runningHandles = null;

        do {
            usleep($sleepTime);
            curl_multi_exec($mh, $runningHandles);
            curl_multi_select($mh);
        }while ($runningHandles > 0);

        $launchMSG = [];

        foreach($requestsList as $key => $row) {
            $launchMSG[$key]['url'] = $row;
            $launchMSG[$key]['err'] = curl_error($handles[$key]);

            if (! empty($launchMSG[$key]['err'])) {
                $launchMSG[$key]['data'] = '';
            } else {
                $launchMSG[$key]['data'] = curl_multi_getcontent($handles[$key]);
            }
            curl_multi_remove_handle($mh, $handles[$key]);
        }

        curl_multi_close($mh);

        var_dump($launchMSG);
    }

    public function launch()
    {
        $file = '../test.tsv';
        $requestsList = [];
        $sleepTime = 10000;
        $requestsList = $this->readRequestList($file);
        $this->postTestRequest($requestsList, $sleepTime);
    }
}

$run = new elasticsearchLoadTest();
$run->launch();
