<?php

namespace OCA\Facturation\service;

class OCApiService {

    private $request;
    private $url = "";
    private $bearerToken = '';
    private $query = [];
    private $rangeStart = 0;
    private $rangeEnd = 19;
    private $allResults = [];
    /** */

    private $headers = [];

    public function __construct($mentorId, $bearerToken, $before, $after) {
        $this->bearerToken = $bearerToken;
        $this->query = [
        'actor' => 'expert',
        'life-cycle-status' => 'canceled,completed,late canceled,marked student as absent',
        'after' => $after,
        'before' => $before,
    ];
        $this->url = "https://api.openclassrooms.com/users/".$mentorId."/sessions";
    }

    public function getOCSessionsAndPresentationsApi() {
        $this->headers = [
            'Authorization: Bearer ' . $this->bearerToken,
            'Accept: application/json',
            'Content-Type: application/json',
            'Range: items=' . $this->rangeStart . '-' . $this->rangeEnd
        ];
        $this->request = curl_init($this->url);

        // Ajouter des options
        curl_setopt($this->request, CURLOPT_RETURNTRANSFER, true);
        // curl_setopt($this->request, CURLOPT_VERBOSE, true); // Enable verbose mode
        // config les headers
        curl_setopt($this->request, CURLOPT_HTTPHEADER, $this->headers);
        // config les query
        curl_setopt($this->request, CURLOPT_URL, $this->url . '?' . http_build_query($this->query));

        $response = curl_exec($this->request);

        if (curl_errno($this->request)) {
            echo 'Error: ' . curl_error($this->request);
            exit;
        }

        $httpcode = curl_getinfo($this->request, CURLINFO_HTTP_CODE);

        echo $this->rangeStart .'/' . $this->rangeEnd ."\n";
        echo $httpcode . "\n";

        if ($httpcode !== 206) {
            file_put_contents('sessions.json', json_encode($this->allResults, JSON_UNESCAPED_UNICODE));
            echo "Total de sessions tous confondues: " . count($this->allResults) . "\n";
            file_put_contents('sessions.php', "<?php\n\nreturn " . var_export($this->allResults, true) . ";\n");
            curl_close($this->request);

            return $this->allResults;
        }

        $this->allResults = array_merge($this->allResults, json_decode($response, true));

        $this->rangeEnd+=20;
        $this->rangeStart+=20;

        $this->getOCSessionsAndPresentationsApi(); // recursive function
    }
}
