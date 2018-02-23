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

    protected function post($location, $body, $headers = [])
    {
        $options = [
            CURLOPT_URL => rtrim($this->apibase, '/') . '/' . $location,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
        ];

        $ch = curl_init();
        curl_setopt_array($ch, ($this->defaultOptions + $options));

        $result = curl_exec($ch);
        print_r($result);
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
            "datatel_supplementalinformationitemid@odata.bind" => "/datatel_supplementalinformationitems($informationItemId)",
            "datatel_prospectid@odata.bind" => "/contacts($contactId)",
            "datatel_name" => "Waitlist Confirmation Form",
            "datatel_submissionstatus" => 1,
            "uwit_processingstatus" => 1,
            "datatel_submissiondate" => (new \DateTime('now'))->format(DATE_ISO8601),
        ];

        $body = json_encode($data, JSON_UNESCAPED_SLASHES);

        $headers = [
            'Content-Type: application/json; charset=7tf-8',
            'OData-MaxVersion: 4.0',
            'OData-Version: 4.0',
            'Accept: application/json',
            'Prefer: odata.include-annotations="*"',
        ];

        print_r($this->post($location, $body, $headers));
    }

    public function attach($syskey, $informationItemName, $documentname, $documentcontent)
    {
        $contactId = $this->getContactId($syskey);

        echo $contactId;
        die();
        $informationItemId = $this->getSupplementalInformationItemId($informationItemName);

        $this->createSupplementalInformationSubmission($contactId, $informationItemId);

        echo $informationItemId;

        return true;

    }
}