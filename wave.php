<?php

/**
 * @license GNU General Public License v2 http://www.gnu.org/licenses/gpl-2.0
 * @author BlueMöhre <bluemoehre@gmx.de>
 * @copyright 2012-2016 BlueMöhre
 * @link http://www.github.com/bluemoehre
 */
class Wave
{
    const ERR_FILE_NOT_SPECIFIED = 'No file specified.';
    const ERR_FILE_OPEN = 'Failed to open file.';
    const ERR_FILE_READ = 'Error while reading from file.';
    const ERR_FILE_WRITE = 'Error while writing to file.';
    const ERR_FILE_CLOSE = 'Failed to close file.';
    const ERR_FILE_INCOMPATIBLE = 'File is not compatible.';
    const ERR_FILE_HEADER_INVALID = 'File header contains invalid data.';

    const SVG_DEFAULT_RESOLUTION_FACTOR = 0.01;

    /**
     * @var string
     */
    protected $file;

    /**
     * @var string
     */
    protected $chunkId;

    /**
     * @var integer
     */
    protected $chunkSize;

    /**
     * @var string
     */
    protected $format;

    /**
     * @var string
     */
    protected $subchunk1Id;

    /**
     * @var integer
     */
    protected $subchunk1Size;

    /**
     * @var integer
     */
    protected $audioFormat;

    /**
     * @var integer
     */
    protected $channels;

    /**
     * @var integer
     */
    protected $sampleRate;

    /**
     * @var integer
     */
    protected $byteRate;

    /**
     * @var integer
     */
    protected $blockAlign;

    /**
     * @var integer
     */
    protected $bitsPerSample;

    /**
     * @var integer
     */
    protected $subchunk2Size;

    /**
     * @var integer
     */
    protected $dataOffset;

    /**
     * @var integer
     */
    protected $kiloBitPerSecond;

    /**
     * @var integer
     */
    protected $totalSamples;

    /**
     * @var float
     */
    protected $totalSeconds;


    /**
     * @param string $file
     */
    public function __construct($file)
    {
        $this->setFile($file);
    }

    /**
     * @param string $file
     * @throws Exception
     */
    public function setFile($file)
    {
        if (empty($file)) throw new UnexpectedValueException(self::ERR_FILE_NOT_SPECIFIED);
        $fileHandle = fopen($file, 'r');
        if ($fileHandle === FALSE) throw new Exception(self::ERR_FILE_OPEN);
        $this->file = $file;

        $chunkId = fread($fileHandle, 4);
        if ($chunkId === FALSE) throw new Exception(self::ERR_FILE_READ);
        if ($chunkId != "RIFF") throw new Exception(self::ERR_FILE_INCOMPATIBLE);
        $this->chunkId = $chunkId;

        $chunkSize = fread($fileHandle, 4);
        if ($chunkSize === FALSE) throw new Exception(self::ERR_FILE_READ);
        $this->chunkSize = unpack('VchunkSize', $chunkSize);

        $format = fread($fileHandle, 4);
        if ($format != "WAVE") throw new Exception(self::ERR_FILE_INCOMPATIBLE);
        $this->format = $format;

        $subChunk1Id = fread($fileHandle, 4);
        if ($subChunk1Id != "fmt ") throw new Exception(self::ERR_FILE_INCOMPATIBLE);
        $this->subchunk1Id = $subChunk1Id;

        $offset = ftell($fileHandle);
        $subChunk1 = fread($fileHandle, 20);
        $subChunk1 = unpack('Vsubchunk1Size/vaudioFormat/vchannels/VsampleRate/VbyteRate/vblockAlign/vbitsPerSample', $subChunk1);
        $this->subchunk1Size = $subChunk1['subchunk1Size'];
        $offset = $offset + 4;
        if ($subChunk1['audioFormat'] != 1) throw new Exception(self::ERR_FILE_INCOMPATIBLE);
        $this->audioFormat = $subChunk1['audioFormat'];
        $this->channels = $subChunk1['channels'];
        $this->sampleRate = $subChunk1['sampleRate'];
        $this->byteRate = $subChunk1['byteRate'];
        $this->blockAlign = $subChunk1['blockAlign'];
        $this->bitsPerSample = $subChunk1['bitsPerSample'];
        if ($this->byteRate != $this->sampleRate * $this->channels * $this->bitsPerSample / 8) throw new Exception(self::ERR_FILE_HEADER_INVALID);
        if ($this->blockAlign != $this->channels * $this->bitsPerSample / 8) throw new Exception(self::ERR_FILE_HEADER_INVALID);

        fseek($fileHandle, $offset + $this->subchunk1Size);
        if (fread($fileHandle, 4) != 'data') throw new Exception(self::ERR_FILE_HEADER_INVALID);

        $subChunk2 = fread($fileHandle, 4);
        $subChunk2 = unpack('VdataSize', $subChunk2);
        $this->subchunk2Size = $subChunk2['dataSize'];
        $this->dataOffset = ftell($fileHandle);

        $this->kiloBitPerSecond = $this->byteRate * 8 / 1000;
        $this->totalSamples = $this->subchunk2Size * 8 / $this->bitsPerSample / $this->channels;
        $this->totalSeconds = $this->subchunk2Size / $this->byteRate;

        fclose($fileHandle);
    }

    /**
     * TODO verify calculations
     * @param float $resolution - Must be <=1. If 1 SVG will be full waveform resolution (amazing large filesize)
     * @param string $targetFile
     * @return string
     * @throws Exception
     */
    public function generateSvg($resolution = self::SVG_DEFAULT_RESOLUTION_FACTOR, $targetFile = null)
    {
        $fileHandle = fopen($this->file, 'r');
        if ($fileHandle === FALSE) throw new Exception(self::ERR_FILE_OPEN);

        $samplesPerMerge = $this->sampleRate / ($resolution * $this->sampleRate);
        $finalSampleVolumes = array();
        $i = 0;
        fseek($fileHandle, $this->dataOffset);

        while (($data = fread($fileHandle, $this->bitsPerSample))) {
            $sample = unpack('svol', $data);
            $samples[] = $sample['vol'];

            // when all samples for a block are collected, start calculation for saving memory
            if ($i > 0 && $i % $samplesPerMerge == 0) {
                // get average peak within this block
                $minValue = min($samples);
                $maxValue = max($samples);
                $finalSampleVolumes[] = array($minValue, $maxValue);
                $samples = array(); // reset
            }
            $i++;

            // TODO analyze side effects
            // skip to increase speed
            fseek($fileHandle, $this->bitsPerSample * $this->channels * 3, SEEK_CUR);
        }

        $minPossibleValue = pow(2, $this->bitsPerSample) / 2 * -1;
        $maxPossibleValue = $minPossibleValue * -1 - 1;
        $range = pow(2, $this->bitsPerSample);
        $svgPathTop = '';
        $svgPathBottom = '';

        foreach ($finalSampleVolumes as $x => $sampleMinMax) {
            $y = round(100 / $range * ($maxPossibleValue - $sampleMinMax[1]));
            $svgPathTop .= "L$x $y";
            $y = round(100 / $range * ($maxPossibleValue + $sampleMinMax[0] * -1));
            $svgPathBottom = "L$x $y" . $svgPathBottom;
        }

        // TODO move gradient to stylesheet
        // TODO this should be improved to use kinda template
        $svg =
            '<svg version="1.1" xmlns="http://www.w3.org/2000/svg" width="' . count($finalSampleVolumes) . 'px" height="100px" preserveAspectRatio="none">' .
            '<defs>' .
            '<linearGradient id="gradient" x1="0%" y1="0%" x2="0%" y2="100%">' .
            '<stop offset="0%" style="stop-color:rgb(0,0,0);stop-opacity:1"/>' .
            '<stop offset="50%" style="stop-color:rgb(50,50,50);stop-opacity:1"/>' .
            '<stop offset="100%" style="stop-color:rgb(0,0,0);stop-opacity:1"/>' .
            '</linearGradient>' .
            '</defs>' .
            '<path d="M0 50' . $svgPathTop . $svgPathBottom . 'L0 50 Z" fill="url(#gradient)"/>' .
            '</svg>';

        if ($targetFile) {
            if (!$fh = fopen($targetFile, 'w')) throw new Exception(self::ERR_FILE_OPEN);
            if (!fwrite($fh, $svg)) throw new Exception(self::ERR_FILE_WRITE);
            if (!fclose($fh)) throw new Exception(self::ERR_FILE_CLOSE);
        }

        return $svg;
    }

    /**
     * @return integer
     */
    public function getChannels()
    {
        return $this->channels;
    }

    /**
     * @return integer
     */
    public function getSampleRate()
    {
        return $this->sampleRate;
    }

    /**
     * @return integer
     */
    public function getByteRate()
    {
        return $this->byteRate;
    }

    /**
     * @return integer
     */
    public function getKiloBitPerSecond()
    {
        return $this->kiloBitPerSecond;
    }

    /**
     * @return integer
     */
    public function getBitsPerSample()
    {
        return $this->bitsPerSample;
    }

    /**
     * @return integer
     */
    public function getTotalSamples()
    {
        return $this->totalSamples;
    }

    /**
     * @param bool $float
     * @return float|int
     */
    public function getTotalSeconds($float = false)
    {
        return $float ? $this->totalSeconds : round($this->totalSeconds);
    }

}
