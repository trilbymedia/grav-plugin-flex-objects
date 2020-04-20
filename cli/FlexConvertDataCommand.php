<?php
namespace Grav\Plugin\Console;

use Grav\Common\Grav;
use Grav\Common\Yaml;
use Grav\Console\ConsoleCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class FlushQueueCommand
 * @package Grav\Console\Cli\
 */
class FlexConvertDataCommand extends ConsoleCommand
{
    /**
     * @var array
     */
    protected $options = [];

    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName('convert-data')
            ->setAliases(['convertdata'])
            ->addOption(
                'in',
                'i',
                InputOption::VALUE_REQUIRED,
                'path to file to convert from (valid types: [json|yaml])'
            )
            ->addOption(
                'out',
                'o',
                InputOption::VALUE_REQUIRED,
                'format of file to convert to [json|yaml]'
            )
            ->setDescription('Converts data from one format to another')
            ->setHelp('The <info>clear-queue-failures</info> command clears any queue failures that have accumulated');
    }

    /**
     * @return int|null|void
     */
    protected function serve()
    {
        $out_raw = null;
        $in = $this->input->getOption('in');
        $in_parts = pathinfo($in);
        $in_extension = $in_parts['extension'];
        $out = $this->input->getOption('out');
        $out_parts = pathinfo($out);
        $out_extension = $out_parts['extension'];

        $io = new SymfonyStyle($this->input, $this->output);

        $io->title('Flex Convert Data');

        if (!file_exists($in)) {
            $io->error('cannot find the file: ' . realpath($in));
            exit;
        }



        if (!$in_extension) {
            $io->error($in . ' has no file extension defined');
            exit;
        }

        if (!$out_extension) {
            $io->error($out_extension . ' is not a valid extension');
            exit;
        }

        $in_raw = file_get_contents($in);

        // Get the input data
        if ($in_extension === 'yaml' || $in_extension === 'yml') {
            $in_data = Yaml::parse($in_raw);
        } elseif ($in_extension === 'json' ) {
            $in_data = json_decode($in_raw, true);
        } else {
            $io->error('input files with extension ' . $in_extension . ', is not supported');
            exit;
        }

        // Simple progress bar
        $progress = new ProgressBar($this->output, count($in_data));
        $progress->setFormat('verbose');
        $progress->start();

        // add Unique Id if needed
        $index = 0;
        $out_data = [];
        foreach ($in_data as $key => $entry) {
            if ($key === $index++) {
                $out_data[$this->generateKey()] = $entry;
            } else {
                $out_data[$key] = $entry;
            }
            $progress->advance();
        }

        // render progress
        $progress->finish();
        $io->newLine(2);
        
        // Convert to output format
        if ($out_extension === 'yaml' || $out_extension === 'yml') {
            $out_raw = Yaml::dump($out_data);
        } elseif ($out_extension === 'json' ) {
            $out_raw = json_encode($out_data, JSON_PRETTY_PRINT);
        } else {
            $io->error('output files with extension ' . $out_extension . ', is not supported');
            exit;
        }

        // Write the file:
        $out_filename = $in_parts['dirname'] . '/' . $in_parts['filename'] . '.' . $out_extension;
        file_put_contents($out_filename, $out_raw);

        $io->success('successfully converted the file and saved as: ' . $out_filename);

    }

    protected function generateKey()
    {
        return substr(hash('sha256', random_bytes(32)), 0, 32);
    }
}
