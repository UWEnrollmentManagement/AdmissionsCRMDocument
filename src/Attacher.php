<?php


namespace UWDOEM\AdmissionsCRMDocument;

use UWDOEM\CRM\Curler\Curler;


class Attacher
{
    /** @var Curler $curler */
    protected $curler = null;

    public function __construct(Curler $curler)
    {
        $this->curler = $curler;
    }


    protected function getContactId($syskey)
    {
        $location = 'contacts';
        $query = [
            '$select' => 'contactid',
            '$filter' => "uwit_sdb_system_key eq '$syskey'",
        ];

        $result = $this->curler->get($location, $query);

        return $result['value'][0]['contactid'];
    }

    protected function getSupplementalInformationItemId($informationItemName)
    {
        $location = 'datatel_supplementalinformationitems';
        $query = [
            '$select' => 'datatel_supplementalinformationitemid',
            '$filter' => "datatel_name eq '$informationItemName'",
        ];

        $result = $this->curler->get($location, $query);
        return $result['value'][0]['datatel_supplementalinformationitemid'];
    }

    protected function createSupplementalInformationSubmission($contactId, $informationItemId, $informationItemName)
    {
        $location = 'datatel_supplementalinformationsubmissions';
        $data = [
            "datatel_suppinfoitem_suppinfosubmission@odata.bind" => "/datatel_supplementalinformationitems($informationItemId)",
            "datatel_contact_datatel_supplementalinformationsu@odata.bind" => "/contacts($contactId)",
            "datatel_name" => $informationItemName,
            "datatel_submissionstatus" => 1,
            "uwit_processingstatus" => 1,
            //"datatel_submissiondate" => (new \DateTime('now'))->format(DATE_ISO8601),
            "datatel_submissiondate" => '2018-02-23T07:32:05.4272Z',
        ];
        $body = json_encode($data, JSON_UNESCAPED_SLASHES);

        $requestHeaders = [
            'Content-Type: application/json; charset=utf-8',
            'OData-MaxVersion: 4.0',
            'OData-Version: 4.0',
            'Accept: application/json',
            'Prefer: odata.include-annotations="*"',
        ];

        $responseHeaders = [];
        $this->curler->post($location, $body, $requestHeaders, $responseHeaders);

        $locationHeader = $responseHeaders['location'][0];
        $supplementalInformationSubmissionId = substr($locationHeader, strpos($locationHeader, '('));
        $supplementalInformationSubmissionId = trim($supplementalInformationSubmissionId, '()');

        return $supplementalInformationSubmissionId;
    }

    protected function attachAnnotation($documentBody, $documentName, $documentMimeType, $supplementalInformationSubmissionId)
    {
        $location = 'annotations';
        $data = [
            "objectid_datatel_supplementalinformationsubmission@odata.bind" => "/datatel_supplementalinformationsubmissions($supplementalInformationSubmissionId)",
            "filename" => $documentName,
            "documentbody" => $documentBody,
            "mimetype" => $documentMimeType,
        ];
        $body = json_encode($data, JSON_UNESCAPED_SLASHES);

        $requestHeaders = [
            'Content-Type: application/json; charset=utf-8',
            'OData-MaxVersion: 4.0',
            'OData-Version: 4.0',
            'Accept: application/json',
            'Prefer: odata.include-annotations="*"',
        ];

        $responseHeaders = [];
        $response = $this->curler->post($location, $body, $requestHeaders, $responseHeaders);

        print_r($response);
        print_r($responseHeaders);

        return $response;

    }

    public function attach($syskey, $informationItemName, $documentBody, $documentName, $documentMimeType)
    {
        $contactId = $this->getContactId($syskey);

        $informationItemId = $this->getSupplementalInformationItemId($informationItemName);
        $supplementalInformationSubmissionId = $this->createSupplementalInformationSubmission($contactId, $informationItemId, $informationItemName);

        $this->attachAnnotation($documentBody, $documentName, $documentMimeType, $supplementalInformationSubmissionId);

        return true;

    }
}