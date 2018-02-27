<?php


namespace UWDOEM\AdmissionsCRMDocument;


class Attacher
{
    protected $apibase;
    protected $apiusername;
    protected $apipassword;
    protected $defaultOptions;

    public function __construct($apibase, $apiusername, $apipassword)
    {
        $this->apibase = $apibase;
        $this->apiusername = $apiusername;
        $this->apipassword = $apipassword;

        $this->defaultOptions = [
            CURLOPT_USERPWD => "{$this->apiusername}:{$this->apipassword}",
            CURLOPT_HTTPAUTH => CURLAUTH_NTLM,
        ];
    }

    protected function get($location, $query = [], $headers = [])
    {
        $options = [
            CURLOPT_URL => rtrim($this->apibase, '/') . '/' . $location . '?' . http_build_query($query),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
        ];

        $ch = curl_init();
        curl_setopt_array($ch, ($this->defaultOptions + $options));

        $result = curl_exec($ch);
        $result = json_decode($result, true);

        curl_close($ch);

        return $result;
    }

    protected function post($location, $body, $requestHeaders = [], &$responseHeaders = [])
    {
        $options = [
            CURLOPT_URL => rtrim($this->apibase, '/') . '/' . $location,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => $requestHeaders,
            CURLOPT_HEADERFUNCTION => function($curl, $header) use (&$responseHeaders)
            {
                $len = strlen($header);
                $header = explode(':', $header, 2);
                if (count($header) < 2) // ignore invalid headers
                    return $len;

                $name = strtolower(trim($header[0]));
                if (!array_key_exists($name, $responseHeaders))
                    $responseHeaders[$name] = [trim($header[1])];
                else
                    $responseHeaders[$name][] = trim($header[1]);

                return $len;
            },
        ];

        $ch = curl_init();
        curl_setopt_array($ch, ($this->defaultOptions + $options));

        $result = curl_exec($ch);
        $result = json_decode($result, true);

        curl_close($ch);

        return $result;

    }

    protected function getContactId($syskey)
    {
        $location = 'contacts';
        $query = [
            '$select' => 'contactid',
            '$filter' => "uwit_sdb_system_key eq '$syskey'",
        ];

        $result = $this->get($location, $query);

        return $result['value'][0]['contactid'];
    }

    protected function getSupplementalInformationItemId($informationItemName)
    {
        $location = 'datatel_supplementalinformationitems';
        $query = [
            '$select' => 'datatel_supplementalinformationitemid',
            '$filter' => "datatel_name eq '$informationItemName'",
        ];

        $result = $this->get($location, $query);
        return $result['value'][0]['datatel_supplementalinformationitemid'];
    }

    protected function createSupplementalInformationSubmission($contactId, $informationItemId)
    {
        $location = 'datatel_supplementalinformationsubmissions';
        $data = [
            "datatel_suppinfoitem_suppinfosubmission@odata.bind" => "/datatel_supplementalinformationitems($informationItemId)",
            "datatel_contact_datatel_supplementalinformationsu@odata.bind" => "/contacts($contactId)",
            "datatel_name" => "Waitlist Confirmation Form",
            "datatel_submissionstatus" => 1,
            "uwit_processingstatus" => 1,
            //"datatel_submissiondate" => (new \DateTime('now'))->format(DATE_ISO8601),
            "datatel_submissiondate" => '2018-02-23T07:32:05.4272Z',
        ];
        $body = json_encode($data, JSON_UNESCAPED_SLASHES);

        $requestHeaders = [
            'Content-Type: application/json; charset=7tf-8',
            'OData-MaxVersion: 4.0',
            'OData-Version: 4.0',
            'Accept: application/json',
            'Prefer: odata.include-annotations="*"',
        ];

        $responseHeaders = [];
        $this->post($location, $body, $requestHeaders, $responseHeaders);

        $locationHeader = $responseHeaders['location'][0];
        $supplementalInformationSubmissionId = substr($locationHeader, strpos($locationHeader, '('));
        $supplementalInformationSubmissionId = trim($supplementalInformationSubmissionId, '()');

        return $supplementalInformationSubmissionId;
    }

    public function attach($syskey, $informationItemName, $documentname, $documentcontent)
    {
        $contactId = $this->getContactId($syskey);

        $informationItemId = $this->getSupplementalInformationItemId($informationItemName);
        $supplementalInformationId = $this->createSupplementalInformationSubmission($contactId, $informationItemId);

        print($supplementalInformationId);


        return true;

    }
}