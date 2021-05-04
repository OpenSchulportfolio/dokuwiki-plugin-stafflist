<?php
/**
 * DokuWiki Plugin stafflist (Helper Component)
 *
 * @license GPL 3 http://www.gnu.org/licenses/gpl-3.0.html
 * @author  Frank Schiebel <frank@talheim.net>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

class  helper_plugin_stafflist extends DokuWiki_Plugin {

    var $Mediadir;
    var $Fieldsequence = array();
    var $Cleancsvfile;
    var $Inputcsvfile;

    function helper_plugin_stafflist() {
        // load helper functions
        global $conf;

        // set mediadir
        $basedir = $conf['savedir'];
        if (!$this->_path_is_absolute($basedir)) {
            $basedir = DOKU_INC . '/' . $basedir;
        }
        $this->Mediadir = $this->_win_path_convert($this->_realpath($basedir.'/media').'/');
	    
    }

    function getMethods() {
        $result = array();
        $result[] = array(
        'name'   => 'stafflist',
        'desc'   => 'Displays stafflist for schools',
        'params' => array(
            'infile' => 'string',
            'outfile' => 'string',
            'number (optional)' => 'integer'
        ),
        'return' => array('pages' => 'array'),
        );
        // and more supported methods...
        return $result;
    }

    /**
     * Creates the stafflist table. Takes the input csv file as argument.
     * First a clean  csv-file will be created, from which the actual
     * table gest rendered. 
     *      
     * @param $staffcsv the input csv-file 
     * @param $params the parameters of the stafflist command
     * @param $targetformat Target format html/json
     * @return string the stafflist output as html/json
     *         
     */
    function create_stafflist($staffcsv, $params) {
        global $conf;
        global $ID;

        // Set file Attributes
        $this->Inputcsvfile = $this->_realpath($this->mediadir . str_replace(':', '/', $staffcsv));
        $this->Cleancsvfile = dirname($this->Inputcsvfile) . "/" . cleanID($this->getConf('cleancsvfile'));

        $staffcsv = $this->Inputcsvfile;

        // set inputcsvfile with path

        $html = "";

	    $showfields = $params["showfields"];
	    $headersubst = $params["headersubst"];

        if ($showfields == "") {
            # FIXME Languages
            $html .= "<div><p><b>Stafflist:</b>Die Feldparameter müssen angegeben werden</p></div>";
            return $html;
        }
        
        $show_fields_array = explode(",",$showfields);
        
        $substheaders = 0;
        if ( $headersubst != "" ) {
            $headersubst_array = explode(",",$headersubst);
            if ( count($show_fields_array) != count($headersubst_array) ) {
                $html .= "<div>Stafflist warning: Zahl von Headersubst stimmt nicht mit Zahl von Fields to Show ueberein!</div>";
            } else {
                $substheaders = 1;
            }
        }
	
	    $delimiter = $this->getConf('delimiter');

    	// create a clean csv file which only contains the fields defined in 
        // the plugin configuration 
    	if ( file_exists ($this->Inputcsvfile) ) {
    		$this->_clean_csv();
    	}
        
        // when the cleaning fails, return with message
	    if ( ! file_exists ($this->Cleancsvfile) ) {
	      	return "No valid clean staffcsvfile found!";
	    } 
	
        // read first line to get fieldnames and 
        // populate the array "$keys_to_show"
	    $handle = fopen($this->Cleancsvfile, "r");
        if ( ($data = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
            foreach ( $show_fields_array as $field ) {
                $key = array_search($field, $data);
                if ( false !== $key ) {
		            $keys_to_show[$field] = $key;
                }
            }
        }
	    fclose($handle);

        // create actual output table
	    $handle = fopen($this->Cleancsvfile, "r");
	    $current_row = 0;
        
        # Start output table
	    $html .= "<table class=\"stafflist\">";

    	while ( ($data = fgetcsv($handle, 0, $delimiter)) !== FALSE ) {
	        
            // first line contains field names. Print tableheaders
            // according to config.
	        if ( $current_row == 0) {
		        if ( $this->getConf('printtableheader') == 1 ) {
                    $html .= "<tr>\n";
		            foreach ( $show_fields_array as $fieldnum => $field ) {
                        if ( $substheaders ) {
			                $html .= "  <th>" . $headersubst_array[$fieldnum] . "</th>\n";
                        } else {
			                $html .= "  <th>" . $field . "</th>\n";
                        }
		            }
		            $html .= "</tr>\n";
		        }
		        $current_row++;
		        continue;
	        }

            $html .= "<tr>\n";
            foreach ( $show_fields_array as $field ) {
		        $html .= "   <td>" . $data[$keys_to_show[$field]] . "</td>\n";
	        }
	        $html .= "</tr>\n";
            
		    $current_row++;
	    }
	    
        $html .= "</table>";
        fclose($handle);
        

	    return $html;
    }
    
    /** 
     * Get json table from cleancsv, get only the configured fields
    */
    function getJsonList() {

	    $delimiter = $this->getConf('delimiter');
	    $publicfields = explode(",",$this->getConf("publicfields"));

        // clean CSV File to read from. 
        // no need to set as attribute, only used locally.
        // path is determined from get uri id
        $cleancsvfile=$this->Mediadir.str_replace(':', '/',cleanID(getID()))."/".cleanID($this->getConf(cleancsvfile));

        $jsonarray = array();

        $handle = fopen($cleancsvfile, "r");
        // get first line
        $data = fgetcsv($handle, 0, $delimiter);
        $numindex = array_flip($data);
    	while ( ($data = fgetcsv($handle, 0, $delimiter)) !== FALSE ) {
           $jsonrow = array();
           foreach ( $publicfields as $fieldname) {
               $jsonrow[]=$data[$numindex[$fieldname]];
            }
            $jsonarray[] = $jsonrow;
        }
        fclose($handle);

        $json=json_encode($jsonarray);
        return $json;
    }


    /**
     * 
     * This function creates a clean csv file from the input csv.
     * Information about the input fields are read from the
     * plugin-config 
     * 
     */
    function _clean_csv() {

	    $delimiter = $this->getConf('delimiter');

        // create array, strip keys
        $cleanfields = $this->_strip_array_keys(explode(",",$this->getConf('cleanfields')));

        // Output arrays
        $cleanrow = array();
	    $cleanoutput = array();

	    $handle = fopen($this->Inputcsvfile, "r");

        // read first line
        $data = fgetcsv($handle, 0, $delimiter);
        $data = $this->_strip_array_keys($data);
        $numindex = array_flip($data);
        
        // push first line to output
        foreach ( $cleanfields as $fieldname) {
               $cleanrow[] = $data[$numindex[$fieldname]];
        }
        $cleanoutput[] = $cleanrow;

        // work on the remaining lines, push to output array
    	while ( ($data = fgetcsv($handle, 0, $delimiter)) !== FALSE ) {

            $cleanrow = array();
            foreach ( $cleanfields as $fieldname) {
               $cleanrow[] = $data[$numindex[$fieldname]];
            }
            $cleanoutput[] = $cleanrow;
	    }
        fclose($handle);

        // write output to cleancsv-file
	    $outfile = fopen("$this->Cleancsvfile", 'w');
        foreach ($cleanoutput as $cleanrow) {
            fputcsv($outfile, $cleanrow);
        }
	    fclose($outfile);


	    // delete staffcsv (data protection)
	    // unlink($this->Inputcsvfile);
    }

    /**
     * Trim all array keys
     *
     * @param $array the array to strip
     * @return $array stripped array
     */
    function _strip_array_keys($toStrip) {
        $a = array_map('trim', array_keys($toStrip));
        $b = array_map('trim', $toStrip);
        $toStrip = array_combine($a, $b);
        return $toStrip;
    }

    /**
     * Converts backslashs in paths to slashs.
     *
     * @param $path the path to convert
     * @return converted path
     */
    function _win_path_convert($path) {
        return str_replace('\\', '/', trim($path));
    }

    /**
     * Determines whether a given path is absolute or relative.
     * On windows plattforms, it does so by checking whether the second character
     * of the path is a :, on all other plattforms it checks for a / as the
     * first character.
     *
     * @param $path the path to check
     * @return true if path is absolute, false otherwise
     */
    function _path_is_absolute($path) {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return ($path[1] == ':');
        } else {
            return ($path[0] == '/');
        }
    }

    /**
     * Canonicalizes a given path. A bit like realpath, but without the resolving of symlinks.
     *
     * @author anonymous
     * @see <http://www.php.net/manual/en/function.realpath.php#73563>
     */
    function _realpath($path) {
        $path=explode('/', $path);
        $output=array();
        for ($i=0; $i<sizeof($path); $i++) {
            if (('' == $path[$i] && $i > 0) || '.' == $path[$i]) continue;
            if ('..' == $path[$i] && $i > 0 && '..' != $output[sizeof($output) - 1]) {
                array_pop($output);
                continue;
            }
            array_push($output, $path[$i]);
        }
        return implode('/', $output);
    }


}


// vim:ts=4:sw=4:et:
