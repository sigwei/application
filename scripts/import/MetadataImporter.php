#!/usr/bin/env php5
<?php

/**
 * This file is part of OPUS. The software OPUS has been originally developed
 * at the University of Stuttgart with funding from the German Research Net,
 * the Federal Department of Higher Education and Research and the Ministry
 * of Science, Research and the Arts of the State of Baden-Wuerttemberg.
 *
 * OPUS 4 is a complete rewrite of the original OPUS software and was developed
 * by the Stuttgart University Library, the Library Service Center
 * Baden-Wuerttemberg, the Cooperative Library Network Berlin-Brandenburg,
 * the Saarland University and State Library, the Saxon State Library -
 * Dresden State and University Library, the Bielefeld University Library and
 * the University Library of Hamburg University of Technology with funding from
 * the German Research Foundation and the European Regional Development Fund.
 *
 * LICENCE
 * OPUS is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the Licence, or any later version.
 * OPUS is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU General Public License for more
 * details. You should have received a copy of the GNU General Public License
 * along with OPUS; if not, write to the Free Software Foundation, Inc., 51
 * Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 *
 * @category    Application
 * @package     Import
 * @author      Sascha Szott <szott@zib.de>
 * @copyright   Copyright (c) 2008-2011, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 * @version     $Id$
 */

require_once dirname(__FILE__) . '/../common/bootstrap.php';
require_once 'Log.php';

class MetadataImporter {

    private $console;

    private $logfile;

    public function run($options) {
        $consoleConf = array('lineFormat' => '[%1$s] %4$s');
        $logfileConf = array('append' => false, 'lineFormat' => '%4$s');
        
        $this->console = Log::factory('console', '', '', $consoleConf, PEAR_LOG_INFO);

        if (count($options) < 2) {
            $this->console->log('Missing parameter: no file to import.');
            return;
        }

        $logfilePath = 'reject.log';
        if (count($options) > 2) { 
            // logfile path is given
            $logfilePath = $options[2];
        }
        $this->logfile = Log::factory('file', $logfilePath, '', $logfileConf, PEAR_LOG_INFO);

        $xml = $this->loadAndValidateInputFile($options[1]);

        $importer = new Opus_Util_MetadataImport($xml, $this->console, $this->logfile);
        $importer->run();
     }

  
    /**
     * Load and validate XML document
     *
     * @param string $filename
     * @return DOMDocument
     */
    private function loadAndValidateInputFile($filename) {
        $this->console->log("Loading XML file '$filename' ...");
        
        if (!is_readable($filename)) {
            $this->console->log("XML file $filename does not exist or is not readable.");
            exit();
        }

        $xml = new DOMDocument();
        if (true !== $xml->load($filename)) {
            $this->console->log("... ERROR: Cannot load XML document $filename: make sure it is well-formed.");
            exit();
        }
        $this->console->log('... OK');

        // Enable user error handling while validating input file
        libxml_clear_errors();
        libxml_use_internal_errors(true);

        $this->console->log("Validate XML file '$filename' ...");
        if (!$xml->schemaValidate(__DIR__ . DIRECTORY_SEPARATOR . 'opus_import.xsd')) {
            $this->console->log("... ERROR: XML document $filename is not valid: " . $this->getErrorMessage());
            exit();
        }
        $this->console->log('... OK');

        return $xml;
    }
}

try {
    $importer = new MetadataImporter();
    $importer->run($argv);
}
catch (Exception $e) {
    echo "\nAn error occurred while importing: " . $e->getMessage() . "\n\n";
    exit();
}
