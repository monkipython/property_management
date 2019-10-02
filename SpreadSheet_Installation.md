# Installing PDF SpreadSheet to Laravel

## Composer Package

Begin by installing the `phpspreadsheet` program from `Composer` using

```
$ composer require phpoffice/phpspreadsheet
```


### Creating a SpreadSheet in PHP

A spreadsheet can be created many different ways using the `phpoffice` API
but the simplest most straightforward is to use a *2-dimensional array* 
and use it to create a `Spreadsheet` instance that will be use to write to a
file.

**Note**: when using spreadsheets you must specify a **starting cell**, for
simplicity simply use a value of `A1` which is the top-left corner.

```
use \PhpOffice\PhpSpreadsheet\Spreadsheet;
use \PhpOffice\PhpSpreadsheet\Writer\{Xlsx,Csv,Xls};
use \PhpOffice\PhpSpreadsheet\IOFactory;

$data = [
    ['header1','header2'],
    ['value1','value2']
];

$sheet =  new Spreadsheet(); //Create spreadsheet instance
$sheet->getActiveSheet()->fromArray($data,NULL,'A1');    //Add data to spreadsheet
```

### Writing to a File

To save the file simply create an `IOWriter` from the `phpoffice` API.
From here you can specify what type of file you want to have saved.

To save to the client's browser simply have the `IOWriter` save to
`php://output` and set the `Content-Disposition` header to be an
`attachment`

```
$writer = IOFactory::createWriter($sheet,'Xlsx'); //Create an Excel  document
header('Content-Disposition: attachment;filename=myfile.xlsx');
$writer->save('php://output');
```