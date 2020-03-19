<?php
class elasticSearchLoadTest {
    private $time;
    private $timeformat;

    public function __construct()
    {
      $this->time = time();
      $this->timeformat = date("Y-m-d", $this->time);

      $file_output = "output/" . $this->timeformat . "-ErrMSG" . ".tsv";
      if (file_exists($file_output)) {
        unlink($file_output);
      }
    }

    private function readRequestList($file, $lineStart, $lineEnd)
    {
        if (! is_file($file)) {
            exit('No Files Exist!');
        }

        $handle = fopen($file, 'r');
        $count = 0;

        if (! $handle) {
            exit('Read Files Fail!');
        }

        $requestsList = [];
        $lineStart -= 1;
        $len = $lineEnd - $lineStart;

        $splFileObject = new SplFileObject($file, 'rb');
        $splFileObject->seek(filesize($file));
        $splFileObject->seek($lineStart);
        while (! ($splFileObject->eof()) && $len) {
            $str = str_replace(array("\r\n", "\r", "\n"), "", $splFileObject->current());
            $requestsList = array_merge($requestsList, (array)$str);
            $splFileObject->next();
            $len--;
        }

//         while (($j++ < $len) && ! feof($handle)) {
//             $data = fgetcsv($handle);
//             var_dump($data);
//             //$requestsList = array_merge($requestsList, $data);
//         }
//         fclose($handle);
// var_dump($requestsList);

        echo "\n" . 'Read: ' . count($requestsList) . ' Row' . "\n" . "\n";
        return $requestsList;
    }

    private function postTestRequest($requestsList, $sleepTime, $cookies, $options = '')
    {
        if (count($requestsList) <= 0) {
            exit('Requestslist Error!' . "\n");
        }

        $handles = [];
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

        $this->outPutErrMSG($launchMSG);
        curl_multi_close($mh);
    }

    private function outPutErrMSG($launchMSG)
    {
        $directory_output_name = "output/";

        if (!is_dir($directory_output_name)){
            mkdir($directory_output_name, 0777, true);
        }
        $file_output = "output/" . $this->timeformat . "-ErrMSG" . ".tsv";
        $file_w = fopen($file_output, "a");

        foreach($launchMSG as $key => $msg) {
            if (! empty($msg['err'])) {
                fwrite($file_w, "{$msg['url']}\t\t\t" . $msg['err'] . "\n");
            }
        }
        fclose($file_w);
    }

    public function launch()
    {
        $file = '../test.tsv';
        $requestsList = [];
        $sleepTime = 0;
        $cookies = 'xxx';
        $lineStart = 1;
        $lineEnd = 3;

        // 毎回間隔時間
        $interval = 1;
        // 実行予定回数
        $number = 4;
        // 実行した回数 (基本0に設定)
        $count = 0;

        if ($interval !== 0) {
            $tmp = $lineEnd;
            do {
                $timeStart = time();
                if ($count !== 0) {
                    usleep($interval);
                    $lineStart = $lineEnd + 1;
                    $lineEnd += $tmp;
                }
                $requestsList = $this->readRequestList($file, $lineStart, $lineEnd);
                $this->postTestRequest($requestsList, $sleepTime, $cookies);
                ++$count;
                $timeEnd = time() - $timeStart;
                echo $count . '回完了!' . '費やした: ' . $timeEnd . "\n";
            } while ($number > $count);
        } else {
            $timeStart = time();
            $requestsList = $this->readRequestList($file, $lineStart, $lineEnd);
            $this->postTestRequest($requestsList, $sleepTime, $cookies);
            $timeEnd = time() - $timeStart;
            echo '総計: ' . $timeEnd . '秒' . "\n";
        }
    }
}

$run = new elasticSearchLoadTest();
$run->launch();
