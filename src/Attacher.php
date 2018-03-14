<?php


namespace UWDOEM\AdmissionsCRMDocument;

use UWDOEM\CRM\Curler\Curler;


class Attacher
{
    /** @var Curler $curler */
    protected $curler = null;

    /** @var string $error */
    protected $error = '';

    /**
     * Attacher constructor.
     *
     * @param Curler $curler
     */
    public function __construct(Curler $curler)
    {
        $this->curler = $curler;
    }

    /**
     * Get an error message from the attacher. Clears the error message.
     *
     * If the attaching process fails, then there may be an error available to get.
     *
     * @return string
     */
    public function getError()
    {
        $error = $this->error;
        $this->error = '';

        return $error;
    }

    /**
     * Retrieves the primary key of a contact in the CRM, given their system key.
     *
     * @param string $systemKey The contact's system key.
     * @return string|false     String on success, false on failure.
     */
    protected function getContactId($systemKey)
    {
        $location = 'contacts';
        $query = [
            '$select' => 'contactid',
            '$filter' => "uwit_sdb_system_key eq '$systemKey'",
        ];

        $result = json_decode($this->curler->get($location, $query), true);

        if (array_key_exists('value', $result) && array_key_exists(0, $result['value'])) {
            return $result['value'][0]['contactid'];
        } else {
            return false;
        }
    }

    /**
     * Get the CRM primary key of a supplemental information item, by the item's name.
     * @param string $informationItemName The name of the supplemental information item.
     * @return string|false               String on success, false on failure.
     */
    protected function getSupplementalInformationItemId($informationItemName)
    {
        $location = 'datatel_supplementalinformationitems';
        $query = [
            '$select' => 'datatel_supplementalinformationitemid',
            '$filter' => "datatel_name eq '$informationItemName'",
        ];

        $result = json_decode($this->curler->get($location, $query), true);
        if (array_key_exists('value', $result) && array_key_exists(0, $result['value'])) {
            return $result['value'][0]['datatel_supplementalinformationitemid'];
        } else {
            return false;
        }
    }

    /**
     * Helper function to retrieve the id of a created entity from the response headers of the POST request
     * used to create that entity.
     *
     * When you create a new entity using POST, the response includes a location header that directs the caller
     * to the address of the newly created entity. The ID of that entity is inside of parentheses.
     *
     * @param array $responseHeaders The response headers received when creating the entity.
     * @return string|false          The entity's ID on success, false on failure.
     */
    protected function getCreatedEntityId($responseHeaders)
    {
        if (array_key_exists('location', $responseHeaders)) {
            $locationHeader = $responseHeaders['location'][0];
            $entityId = substr($locationHeader, strpos($locationHeader, '('));
            $entityId = trim($entityId, '()');

            return $entityId;
        } else {
            return false;
        }
    }

    /**
     * Create a supplemental information submission for a contact.
     *
     * @param string $contactId           The CRM primary key of the contact to record the submission for.
     * @param string $informationItemId   The id obtained from getSupplementalInformationItemId()
     * @param string $informationItemName A name for the information item.
     * @return string|false
     */
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
        $responseCode = -1;
        $this->curler->post($location, [], $body, $requestHeaders, [], $responseHeaders, $responseCode);

        if ($responseCode >= 200 && $responseCode < 300) {
            return $this->getCreatedEntityId($responseHeaders);
        } else {
            return false;
        }
    }

    /**
     * Attach a document body to a supplemental information submission item.
     *
     * @param string $documentBody                        The document of the body to attach, base64 encoded.
     * @param string $documentName                        The file name of the file to attach.
     * @param string $documentMimeType                    The document mime type of the file.
     * @param string $supplementalInformationSubmissionId The Id obtained from createSupplementalInformationSubmission.
     * @return string|false                               The Id of the annotation crated, or false on failure.
     */
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
        $responseCode = -1;
        $this->curler->post($location, [], $body, $requestHeaders, [], $responseHeaders, $responseCode);

        if ($responseCode >= 200 && $responseCode < 300) {
            return $this->getCreatedEntityId($responseHeaders);
        } else {
            return false;
        }
    }

    /**
     * Attach a document to a student's CRM record.
     *
     * @param string $systemKey           The SDB system key of the student to attach this record to.
     * @param string $informationItemName Must correspond to an existing supplemental information item name in the CRM.
     * @param string $documentBody        The body the document to attach, encoded in base64.
     * @param string $documentName        The file name of the document to attach.
     * @param string $documentMimeType    The mime type of the document to attach.
     * @return string|false               The Id of the created annotation on success, or false on failure.
     */
    public function attach($systemKey, $informationItemName, $documentBody, $documentName, $documentMimeType)
    {
        $contactId = $this->getContactId($systemKey);
        if ($contactId === false) {
            $this->error = 'Failed to find contact for given system key.';
            return false;
        }

        $informationItemId = $this->getSupplementalInformationItemId($informationItemName);
        if ($informationItemId === false) {
            $this->error = "Failed to find supplement information item with given name: '$informationItemName'.";
            return false;
        }

        $supplementalInformationSubmissionId = $this->createSupplementalInformationSubmission($contactId, $informationItemId, $informationItemName);
        if ($supplementalInformationSubmissionId === false) {
            $this->error = 'Failed to create supplemental information submission.';
            return false;
        }

        $annotationId = $this->attachAnnotation($documentBody, $documentName, $documentMimeType, $supplementalInformationSubmissionId);
        if ($annotationId === false) {
            $this->error = 'Failed to create annotation.';
            return false;
        }

        return $annotationId;
    }
}