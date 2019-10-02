<?php
namespace App\Library;
use \PhpOffice\PhpSpreadsheet\Spreadsheet;
use \PhpOffice\PhpSpreadsheet\Writer\{Xlsx,Csv,Xls};
use \PhpOffice\PhpSpreadsheet\IOFactory;


class SpreadSheetGenerator {    
    private static $_outputBuffer = 'php://output';
    private static $_startingCell = 'A1';
    
//------------------------------------------------------------------------------
/*
 * @desc Generates a spreadsheet from the grid table data that was sent by the
 *  client view and generates a spreadsheet and saves to the client browser
 * @params {array} Record data retrieved from Elastic search
 * @params {string} What type of file that is being generated
 * @params {string} Name of the file being generated and saved
 * @returns {object} response object
 * 
 */    
    public static function generateSheet($data,$fileType,$fileName='test'){
        //Gather which fields that need to be retrieved from every elastic search record
        $selectedCols = $data['selectedField'];
        
        //Gather column override functions for the corresponding view
        $overrides    = $data['columnOverrides'];
        //Have the headers of the data sheet be the column names retrieved from
        //the bootstrap table from the corresponding view
        //This would make them the first row of the spreadsheet
        $sheetData = [array_map(function($item){return $item['title'];},$selectedCols)];
        
        //Obtain all rows from Elasticsearch
        $r = $data['hits']['hits'];
        
        foreach($r as $i => $row){
            //Get individual table / elastic search record
            $source = $row['_source'];
            
            //Data cells for the spreadsheet data
            //Is modeled easily as a 2D array
            $sheetRow = [];
            
            //Retrieve every column value from elastic search record
            foreach($selectedCols as $c){
                //Have the number field be the currently iterated row
                if($c['field'] == 'num'){
                  $sheetRow[] = $i + 1;
                  //If the field name is specified in the column overrides array
                  //override the value using the function that it corresponds to in the
                  //override array
                } else if(isset($overrides[$c['field']])){
                  $sheetRow[] = $overrides[$c['field']]($source);
                  //Otherwise just extract the field from the elastic search record directly
                } else if(isset($source[$c['field']])) {
                  $sheetRow[] = $source[$c['field']];
                } else {
                  //If the field does not exist in the Elastic search record simply 
                  //set the column to a blank string
                  $sheetRow[] = '';
                }
            }
            
            //Add row to the spreadsheet data
            $sheetData[] = $sheetRow;
        }
        
        //Create a spreadsheet instance
        $sheet = new Spreadsheet();
        
        //Generate spreadsheet from processed data from Elastic search
        $sheet->getActiveSheet()->fromArray($sheetData,NULL,self::$_startingCell);
        
        //Create a spreadsheet writer for the corresponding file type
        $writer = IOFactory::createWriter($sheet,ucfirst($fileType));
        
        //Set file enclosure if the file type is '.csv'
        self::_setFileEnclosures($fileType, $writer);
        
        //For now have the response be sent as a file attachment that will download once clicked
        header('Content-Disposition: attachment;filename=' . $fileName . '.' . $fileType);
        
        //Save to buffer by default it will be the browser
        $writer->save(self::$_outputBuffer);
        $response = ['success'=>true,'file'=>$fileName . '.' . $fileType];
        return $response;
    }
//------------------------------------------------------------------------------
    /*
     * @desc Sets file writer options for writing data to certain file types
     *  such as .csv
     * @params {string} $fileType The file type of the spreadsheet that is being generated
     * @params {object} $writer Spreadsheet writer instance
     * @returns {void}
     */
    private static function _setFileEnclosures($fileType,$writer){
        switch($fileType){
          //Csv Files need to have their enclosure and line ending sets
          //so rows are not wrapped in double quotes ""
            case 'csv':
                $writer->setEnclosure(' ');
                $writer->setLineEnding("\r\n");
                $writer->setSheetIndex(0);
                break;
        }
    }
}