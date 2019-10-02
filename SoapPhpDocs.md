# Making SOAP API Calls with PHP

## Using SoapClient

Soap Calls can be made easily using normal `php` syntax i.e 
`arrays`, `strings`, `stdClass` by using the `SoapClient` library.

* Install with `composer` as well as install the `ImageMagick` **PHP Library**:

```
$ sudo apt-get update && sudo apt-get install -y php-imagick
$ composer require zendframework/zend-soap
$ composer require intervention/image
$ composer require intervention/imagecache
```

### Instantiation

Instantiate the **Soap Client** with the *API URL* and add the url parameter
`?WSDL` to make the call.

For debugging set `"trace"=>1` in the options and the `"soap_version"=>SOAP_1_1`

```php
use \SoapClient;
$url     = 'https://pmc-rps01:8080/xarcapi/Application?wsdl';
$options = ['trace'=>1,'soap_version'=>SOAP_1_1];

$client  = new SoapClient($url,$options);
```

## Making Calls with SoapClient
Specific calls can be made using **instance methods* on the **Soap Client Instance Object**. Any parameters to the method are specified using *associative arrays*.

An example below makes a call to `GetDocument` on the api for the URL shown above.

```php
$drn       = '201903200035000003';
$url       = 'https://pmc-rps01:8080/xarcapi/Application?wsdl';
$reqParams = ['drn' => $drn];

$fnName    = 'GetDocument';
$resObject = $client->$fnName($reqParams);
```

* **Output**

```php
{#3190
     +"GetDocumentResult": {#3199
       +"ErrorMessage": null,
       +"ErrorStack": null,
       +"Result": "Success",
       +"Document": {#3201
         +"Account": "00008677",
         +"Amount": "$40.00",
         +"AmountOpId": 13375,
         +"AmountValue": 4000,
         +"Annotations": {#3197},
         +"Audits": {#3191},
         +"BalanceStatus": "Y",
         +"CARConf": "0",
         +"CARDigits": "0000000400",
         +"DepositBundleId": 69663,
         +"DepositBundleSeq": 1,
         +"Drn": "201903220035000003",
         +"EndpointType": "ICL",
         +"FiOffset": 10560,
         +"FiSize": 21502,
         +"Fields": {#3195},
         +"ForceBalanced": null,
         +"FormClassId": 2,
         +"FormDefId": 1,
         +"FormName": null,
         +"InCarrier": null,
         +"JobKey": "201903220035",
         +"KeyAmount": "$40.00",
         +"KeyAmountOpId": 13375,
         +"KeyAmountOpName": null,
         +"KeyAmountValue": 4000,
         +"Matched": null,
         +"OrgId": 0,
         +"Pass1Endorsement": "",
         +"Pass2Endorsement": "FOR DEPOSIT ONLY\nPAMA Management\n8003090373/169\n",
         +"PocketGroup": "AA26-8003090373-0468",
         +"ProofTypeId": 2,
         +"PymtType": "O",
         +"ReasonCode": 0,
         +"Reject": null,
         +"Reversed": null,
         +"RiOffset": 32062,
         +"RiSize": 16126,
         +"StartUserDocNum": 0,
         +"TransDocCount": 0,
         +"TransId": 1,
         +"TransType": "M",
         +"UserDocNum": 0,
       },
     },
}
```
The response object will be of **type:** `\stdClass`.

To get access the most recent request as `xml` simply call:

```php
$xml = $client->__getLastRequest();
```

* **Output (Request as SOAP XML)**:

```xml
<?xml version="1.0" encoding="UTF-8"?>\n
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://tempuri.org/">
    <SOAP-ENV:Body>
        <ns1:GetDocument>
            <ns1:drn>201903220035000003</ns1:drn>
        </ns1:GetDocument>
    </SOAP-ENV:Body>
</SOAP-ENV:Envelope>
```
* To see what **methods** are available for the api simply use:

```php
$fns   = $client->__getFunctions();
```

### Available Functions

* **Output**:

```php
[
     "GetAllLockboxesResponse GetAllLockboxes(GetAllLockboxes $parameters)",
     "GetBatchResponse GetBatch(GetBatch $parameters)",
     "GetDocumentResponse GetDocument(GetDocument $parameters)",
     "GetFormsResponse GetForms(GetForms $parameters)",
     "GetImageResponse GetImage(GetImage $parameters)",
     "GetTransactionResponse GetTransaction(GetTransaction $parameters)",
     "SearchByAccountResponse SearchByAccount(SearchByAccount $parameters)",
     "SearchByAmountResponse SearchByAmount(SearchByAmount $parameters)",
     "SearchByAppIdResponse SearchByAppId(SearchByAppId $parameters)",
     "SearchByCheckDataResponse SearchByCheckData(SearchByCheckData $parameters)",
     "SearchByDRNResponse SearchByDRN(SearchByDRN $parameters)",
     "SearchByFldResponse SearchByFld(SearchByFld $parameters)",
     "SearchBySpecificFldResponse SearchBySpecificFld(SearchBySpecificFld $parameters)",
]
```


### Available Types

* To see what the *syntax and types* should be used for API simply call:

```php
$types = $client->__getTypes();
```

* **Output**:

```php
[
     """
       struct GetAllLockboxes {\n
       }
       """,
     """
       struct GetAllLockboxesResponse {\n
        LockboxResult GetAllLockboxesResult;\n
       }
       """,
     """
       struct GetBatch {\n
        string jobKey;\n
       }
       """,
     """
       struct GetBatchResponse {\n
        BatchResult GetBatchResult;\n
       }
       """,
     """
       struct GetDocument {\n
        string drn;\n
       }
       """,
     """
       struct GetDocumentResponse {\n
        DocumentResult GetDocumentResult;\n
       }
       """,
     """
       struct GetForms {\n
        long lockboxId;\n
       }
       """,
     """
       struct GetFormsResponse {\n
        FormResult GetFormsResult;\n
       }
       """,
     """
       struct GetImage {\n
        string drn;\n
       }
       """,
     """
       struct GetImageResponse {\n
        ImageResult GetImageResult;\n
       }
       """,
     """
       struct GetTransaction {\n
        string jobKey;\n
        long transId;\n
       }
       """,
     """
       struct GetTransactionResponse {\n
        TransactionResult GetTransactionResult;\n
       }
       """,
     """
       struct SearchByAccount {\n
        long lockboxId;\n
        string accountNumber;\n
        string procStartDate;\n
        string procEndDate;\n
       }
       """,
     """
       struct SearchByAccountResponse {\n
        SearchResult SearchByAccountResult;\n
       }
       """,
     """
       struct SearchByAmount {\n
        long lockboxId;\n
        long amount;\n
        string procStartDate;\n
        string procEndDate;\n
       }
       """,
     """
       struct SearchByAmountResponse {\n
        SearchResult SearchByAmountResult;\n
       }
       """,
     """
       struct SearchByAppId {\n
        long lockboxId;\n
        long AppId;\n
        string startDate;\n
        string endDate;\n
       }
       """,
     """
       struct SearchByAppIdResponse {\n
        SearchResult SearchByAppIdResult;\n
       }
       """,
     """
       struct SearchByCheckData {\n
        long lockboxId;\n
        string startDate;\n
        string endDate;\n
        string tranCode;\n
        string accountNumber;\n
        string onUs;\n
        string routingTransit;\n
        string auxOnUs;\n
       }
       """,
     """
       struct SearchByCheckDataResponse {\n
        SearchResult SearchByCheckDataResult;\n
       }
       """,
     """
       struct SearchByDRN {\n
        long lockboxId;\n
        string DRN;\n
       }
       """,
     """
       struct SearchByDRNResponse {\n
        SearchResult SearchByDRNResult;\n
       }
       """,
     """
       struct SearchByFld {\n
        long lockboxId;\n
        string fieldData;\n
        string procStartDate;\n
        string procEndDate;\n
       }
       """,
     """
       struct SearchByFldResponse {\n
        SearchResult SearchByFldResult;\n
       }
       """,
     """
       struct SearchBySpecificFld {\n
        long lockboxId;\n
        string fieldData;\n
        string procStartDate;\n
        string procEndDate;\n
        long fieldId;\n
       }
       """,
     """
       struct SearchBySpecificFldResponse {\n
        SearchResult SearchBySpecificFldResult;\n
       }
       """,
     "int char",
     "duration duration",
     "string guid",
     """
       struct LockboxResult {\n
        ArrayOfLockbox Lockboxes;\n
       }
       """,
     """
       struct ServiceResult {\n
        string ErrorMessage;\n
        string ErrorStack;\n
        ServiceResultEnum Result;\n
       }
       """,
     "string ServiceResultEnum",
     """
       struct BatchResult {\n
        Job JobInfo;\n
       }
       """,
     """
       struct DocumentResult {\n
        Doc Document;\n
       }
       """,
     """
       struct FormResult {\n
        ArrayOfFormDef Forms;\n
       }
       """,
     """
       struct ImageResult {\n
        ImageInfo Image;\n
       }
       """,
     """
       struct TransactionResult {\n
        ArrayOfDoc Documents;\n
       }
       """,
     """
       struct SearchResult {\n
        ArrayOfSearchResultItem FoundItems;\n
        long TotalHits;\n
       }
       """,
     """
       struct ArrayOfLockbox {\n
        Lockbox Lockbox;\n
       }
       """,
     """
       struct Lockbox {\n
        ArrayOfKeyValueOflongFormDef4xXbsWAs Forms;\n
        decimal LockboxId;\n
        string LockboxName;\n
       }
       """,
     """
       struct FormDef {\n
        ArrayOfKeyValueOflongFieldDef4xXbsWAs Fields;\n
        decimal FormClassId;\n
        decimal FormDefinitionId;\n
        string FormName;\n
       }
       """,
     """
       struct FieldDef {\n
        string DisplayOrder;\n
        decimal FieldId;\n
        string FieldName;\n
        string FieldType;\n
       }
       """,
     """
       struct Job {\n
        string Amount;\n
        long AmountValue;\n
        long ApplicationId;\n
        string ArExtracted;\n
        ArrayOfAuditTran Audits;\n
        string BatchNumber;\n
        string BeginTime;\n
        ArrayOfDoc Documents;\n
        string EndTime;\n
        string Exported;\n
        string ImageFileName;\n
        string JobKey;\n
        long LockBoxId;\n
        long NumItems;\n
        string PostDate;\n
        string ProcDate;\n
        string RunFileName;\n
        string StationId;\n
        string SysDate;\n
        string User1;\n
        string User2;\n
        string User3;\n
        string User4;\n
       }
       """,
     """
       struct ArrayOfDoc {\n
        Doc Doc;\n
       }
       """,
     """
       struct Doc {\n
        string Account;\n
        string Amount;\n
        long AmountOpId;\n
        long AmountValue;\n
        ArrayOfAnnotation Annotations;\n
        ArrayOfAuditTran Audits;\n
        string BalanceStatus;\n
        string CARConf;\n
        string CARDigits;\n
        long DepositBundleId;\n
        long DepositBundleSeq;\n
        string Drn;\n
        string EndpointType;\n
        long FiOffset;\n
        long FiSize;\n
        ArrayOfField Fields;\n
        string ForceBalanced;\n
        long FormClassId;\n
        long FormDefId;\n
        string FormName;\n
        string InCarrier;\n
        string JobKey;\n
        string KeyAmount;\n
        long KeyAmountOpId;\n
        string KeyAmountOpName;\n
        long KeyAmountValue;\n
        string Matched;\n
        long OrgId;\n
        string Pass1Endorsement;\n
        string Pass2Endorsement;\n
        string PocketGroup;\n
        long ProofTypeId;\n
        string PymtType;\n
        long ReasonCode;\n
        string Reject;\n
        string Reversed;\n
        long RiOffset;\n
        long RiSize;\n
        long StartUserDocNum;\n
        long TransDocCount;\n
        long TransId;\n
        string TransType;\n
        long UserDocNum;\n
       }
       """,
     """
       struct ArrayOfField {\n
        Field Field;\n
       }
       """,
     """
       struct Field {\n
        string DRN;\n
        long FieldId;\n
        string FieldValue;\n
        long ZoneId;\n
       }
       """,
     """
       struct ArrayOfFormDef {\n
        FormDef FormDef;\n
       }
       """,
     """
       struct ImageInfo {\n
        base64Binary FrontImage;\n
        base64Binary RearImage;\n
       }
       """,
     """
       struct ArrayOfSearchResultItem {\n
        SearchResultItem SearchResultItem;\n
       }
       """,
     """
       struct SearchResultItem {\n
        string Account;\n
        long Amount;\n
        long ApplicationId;\n
        string Drn;\n
        long FormDefinitionId;\n
        string JobKey;\n
        long LockboxId;\n
        string PostDate;\n
        string ProcDate;\n
        long ProofTypeId;\n
        long TransId;\n
       }
       """,
     """
       struct ArrayOfKeyValueOflongFormDef4xXbsWAs {\n
        KeyValueOflongFormDef4xXbsWAs KeyValueOflongFormDef4xXbsWAs;\n
       }
       """,
     """
       struct KeyValueOflongFormDef4xXbsWAs {\n
        long Key;\n
        FormDef Value;\n
       }
       """,
     """
       struct ArrayOfKeyValueOflongFieldDef4xXbsWAs {\n
        KeyValueOflongFieldDef4xXbsWAs KeyValueOflongFieldDef4xXbsWAs;\n
       }
       """,
     """
       struct KeyValueOflongFieldDef4xXbsWAs {\n
        long Key;\n
        FieldDef Value;\n
       }
       """,
     """
       struct ArrayOfAuditTran {\n
        AuditTran AuditTran;\n
       }
       """,
     """
       struct AuditTran {\n
        string AppCode;\n
        string AuditEventID;\n
        long AuditId;\n
        string DRN;\n
        string EventDate;\n
        string EventTime;\n
        string JobKey;\n
        long LockboxID;\n
        string MsgText;\n
        int OpID;\n
        string OpName;\n
        string ProcDate;\n
        int ReasonCode;\n
        string WorkStation;\n
       }
       """,
     """
       struct ArrayOfAnnotation {\n
        Annotation Annotation;\n
       }
       """,
     """
       struct Annotation {\n
        long AnnotationId;\n
        string CreationDate;\n
        string CreationTime;\n
        string Creator;\n
        string Drn;\n
        double Height;\n
        string ImageGuide;\n
        string ImageSide;\n
        string MsgText;\n
        boolean NewAnnotation;\n
        ImageSideType Side;\n
        long TransId;\n
        long Transparency;\n
        double Width;\n
        double X;\n
        double Y;\n
        string strAnnotationType;\n
       }
       """,
     "string ImageSideType",
     """
       struct Comment {\n
       }
       """,
     """
       struct Mask {\n
       }
       """,
     """
       struct Redaction {\n
       }
       """,
     """
       struct Sticker {\n
       }
       """,
     """
       struct Note {\n
       }
       """,
     """
       struct ZoneBox {\n
       }
       """,
     """
       struct Bookmark {\n
       }
       """,
     """
       struct Highlight {\n
       }
       """,
   ]
```


