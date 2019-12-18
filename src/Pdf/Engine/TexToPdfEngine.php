<?php
declare(strict_types=1);

namespace CakePdf\Pdf\Engine;

use Cake\Core\Exception\Exception;
use CakePdf\Pdf\CakePdf;

class TexToPdfEngine extends AbstractPdfEngine
{
    /**
     * Path to the tex binary of your choice.
     *
     * @var string
     */
    protected $_binary = '/usr/bin/latexpdf';

    /**
     * Constructor
     *
     * @param \CakePdf\Pdf\CakePdf $Pdf CakePdf instance
     */
    public function __construct(CakePdf $Pdf)
    {
        parent::__construct($Pdf);

        $this->_defaultConfig['options']['output-directory'] = TMP . 'pdf';
    }

    /**
     * Write the tex file.
     *
     * @return string Returns the file name of the written tex file.
     */
    protected function _writeTexFile()
    {
        $output = $this->_Pdf->html();
        $file = sha1($output);
        $texFile = $this->getConfig('options.output-directory') . DIRECTORY_SEPARATOR . $file;
        file_put_contents($texFile, $output);

        return $texFile;
    }

    /**
     * Clean up the files generated by tex.
     *
     * @param string $texFile Tex file name.
     * @return void
     */
    protected function _cleanUpTexFiles($texFile)
    {
        $extensions = ['aux', 'log', 'pdf'];
        foreach ($extensions as $extension) {
            $texFile = $texFile . '.' . $extension;
            if (file_exists($texFile)) {
                unlink($texFile);
            }
        }
    }

    /**
     * Generates Pdf from html
     *
     * @throws \Cake\Core\Exception\Exception
     * @return string raw pdf data
     */
    public function output()
    {
        $texFile = $this->_writeTexFile();
        $content = $this->_exec($this->_getCommand(), $texFile);

        if (strpos(mb_strtolower($content['stderr']), 'error')) {
            throw new Exception("System error <pre>" . $content['stderr'] . "</pre>");
        }

        if (mb_strlen($content['stdout'], $this->_Pdf->encoding()) === 0) {
            throw new Exception("TeX compiler binary didn't return any data");
        }

        if ((int)$content['return'] !== 0 && !empty($content['stderr'])) {
            throw new Exception("Shell error, return code: " . (int)$content['return']);
        }

        $result = (string)file_get_contents($texFile . '.pdf');
        $this->_cleanUpTexFiles($texFile);

        return $result;
    }

    /**
     * Execute the latex binary commands for rendering pdfs
     *
     * @param string $cmd the command to execute
     * @param string $input Html to pass to wkhtmltopdf
     * @return array the result of running the command to generate the pdf
     */
    protected function _exec($cmd, $input)
    {
        $cmd .= ' ' . $input;

        $result = ['stdout' => '', 'stderr' => '', 'return' => ''];

        $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        fwrite($pipes[0], $input);
        fclose($pipes[0]);

        $result['stdout'] = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $result['stderr'] = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $result['return'] = proc_close($proc);

        return $result;
    }

    /**
     * Builds the command.
     *
     * @return string The command with params and options.
     */
    protected function _buildCommand()
    {
        $command = $this->_binary;
        $options = (array)$this->getConfig('options');
        foreach ($options as $key => $value) {
            if (empty($value)) {
                continue;
            } elseif ($value === true) {
                $command .= ' --' . $key;
            } else {
                $command .= sprintf(' --%s %s', $key, escapeshellarg($value));
            }
        }

        return $command;
    }

    /**
     * Get the command to render a pdf
     *
     * @return string the command for generating the pdf
     * @throws \Cake\Core\Exception\Exception
     */
    protected function _getCommand()
    {
        $binary = $this->getConfig('binary');

        if ($binary) {
            $this->_binary = $binary;
        }
        if (!is_executable($this->_binary)) {
            throw new Exception(sprintf('TeX compiler binary is not found or not executable: %s', $this->_binary));
        }

        $options = (array)$this->getConfig('options');

        if (!is_dir($options['output-directory'])) {
            mkdir($options['output-directory']);
        }

        return $this->_buildCommand();
    }
}
