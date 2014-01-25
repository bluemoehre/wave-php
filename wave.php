<?php

/**
 * @license GNU General Public License v2 http://www.gnu.org/licenses/gpl-2.0
 * @author BlueMöhre <bluemoehre@gmx.de>
 * @copyright 2012-2014 BlueMöhre
 * @link http://www.github.com/bluemoehre
 *
 * todo add PHPDoc comments
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

    private $file;

    // existing values
    private $chunkId;
    private $chunkSize;
    private $format;
    private $subchunk1Id;
    private $subchunk1Size;
    private $audioFormat;
    private $channels;
    private $sampleRate;
    private $byteRate;
    private $blockAlign;
    private $bitsPerSample;
    private $subchunk2Size;

    // calculated values
    private $dataOffset;
    private $kiloBitPerSecond;

    private $totalSamples;
    private $totalSeconds;
//    private $samples;
//    private $seconds;
//    private $minutes;
//    private $hours;

    private $resolutionFactor = 0.01;


    public function __construct($file)
    {
        $this->setFile($file);
    }


    public function setFile($file)
    {
        if (empty($file)) throw new Exception(self::ERR_FILE_NOT_SPECIFIED);
        $fileHandle = @fopen($file, 'r');
        if ($fileHandle === FALSE) throw new Exception(self::ERR_FILE_OPEN);
        $this->file = $file;

        $chunkId = @fread($fileHandle,4);
        if ($chunkId === FALSE) throw new Exception(self::ERR_FILE_READ);
        if ($chunkId != "RIFF") throw new Exception(self::ERR_FILE_INCOMPATIBLE);
        $this->chunkId = $chunkId;

        $chunkSize = @fread($fileHandle,4);
        if ($chunkSize === FALSE) throw new Exception(self::ERR_FILE_READ);
        $this->chunkSize = unpack('VchunkSize',$chunkSize);

        $format = @fread($fileHandle,4);
        if ($format != "WAVE") throw new Exception(self::ERR_FILE_INCOMPATIBLE);
        $this->format = $format;

        $subchunk1Id = @fread($fileHandle,4);
        if ($subchunk1Id != "fmt ") throw new Exception(self::ERR_FILE_INCOMPATIBLE);
        $this->subchunk1Id = $subchunk1Id;

        $offset = ftell($fileHandle);
        $subchunk1 = fread($fileHandle, 20);
        $subchunk1 = unpack('Vsubchunk1Size/vaudioFormat/vchannels/VsampleRate/VbyteRate/vblockAlign/vbitsPerSample',$subchunk1);
        $this->subchunk1Size = $subchunk1['subchunk1Size'];
        $offset = $offset + 4;
        if ($subchunk1['audioFormat'] != 1) throw new Exception(self::ERR_FILE_INCOMPATIBLE);
        $this->audioFormat = $subchunk1['audioFormat'];
        $this->channels = $subchunk1['channels'];
        $this->sampleRate = $subchunk1['sampleRate'];
        $this->byteRate = $subchunk1['byteRate'];
        $this->blockAlign = $subchunk1['blockAlign'];
        $this->bitsPerSample = $subchunk1['bitsPerSample'];
        if ($this->byteRate != $this->sampleRate * $this->channels * $this->bitsPerSample / 8) throw new Exception(self::ERR_FILE_HEADER_INVALID);
        if ($this->blockAlign != $this->channels * $this->bitsPerSample / 8) throw new Exception(self::ERR_FILE_HEADER_INVALID);

        @fseek($fileHandle, $offset + $this->subchunk1Size);
        if (@fread($fileHandle,4) != 'data') throw new Exception(self::ERR_FILE_HEADER_INVALID);

        $subchunk2 = fread($fileHandle, 4);
        $subchunk2 = unpack('VdataSize',$subchunk2);
        $this->subchunk2Size = $subchunk2['dataSize'];
        $this->dataOffset = @ftell($fileHandle);

        $this->kiloBitPerSecond = $this->byteRate * 8 / 1000;
        $this->totalSamples = $this->subchunk2Size * 8 / $this->bitsPerSample / $this->channels;
        $this->totalSeconds = round($this->subchunk2Size / $this->byteRate);
        @fclose($fileHandle);
    }

    // These calculations maybe badly wrong
    public function generateSvg($targetFile)
    {
        $fileHandle = @fopen($this->file, 'r');
        if ($fileHandle === FALSE) throw new Exception(self::ERR_FILE_OPEN);
        $samplesPerMerge = $this->sampleRate / ($this->resolutionFactor * $this->sampleRate);
        $finalSampleVolumes = array();
        $i = 0;
        fseek($fileHandle,$this->dataOffset);
        while (($data = @fread($fileHandle, $this->bitsPerSample)))
        {
            $sample = unpack('svol',$data);
            $samples[] = $sample['vol'];

            // when all samples for a block are collected, start calculation for saving memory
            if ($i > 0 && $i % $samplesPerMerge == 0)
            {
                # Durchschnittswert innerhalb dieses Blocks bestimmen
                $minValue = min($samples);
                $maxValue = max($samples);
                $finalSampleVolumes[] = array($minValue, $maxValue);
                $samples = array(); # Speicher aufräumen
            }
            $i++;

            fseek($fileHandle, $this->bitsPerSample * $this->channels * 3, SEEK_CUR); # skip to increase speed
        }

        $minPossibleValue = pow(2,$this->bitsPerSample) / 2 * -1;
        $maxPossibleValue = $minPossibleValue * -1 - 1;
        $range = pow(2,$this->bitsPerSample);

        // todo move gradient to stylesheet
        // todo this should be improved to use kinda template
        // ---------- SVG code ----------
        $svg =
            '<?xml version="1.0"?>'.
            '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">'.
            '<svg version="1.1" xmlns="http://www.w3.org/2000/svg" width="'.count($finalSampleVolumes).'px" height="100px" preserveAspectRatio="none">'.
            '<defs>'.
            '<linearGradient id="gradient" x1="0%" y1="0%" x2="0%" y2="100%">'.
            '<stop offset="0%" style="stop-color:rgb(0,0,0);stop-opacity:1"/>'.
            '<stop offset="50%" style="stop-color:rgb(50,50,50);stop-opacity:1"/>'.
            '<stop offset="100%" style="stop-color:rgb(0,0,0);stop-opacity:1"/>'.
            '</linearGradient>'.
            '</defs>'.
            '<path d="M0 50';
        foreach ($finalSampleVolumes AS $i => $sampleMinMax)
        {
            $y = round(100 / $range * ($maxPossibleValue - $sampleMinMax[1]));
            $svg .= 'L'.$i.' '.$y;
            $bottom[] = round(100 / $range * ($maxPossibleValue + $sampleMinMax[0] * -1));
        }
        $bottom = array_reverse($bottom);
        foreach ($bottom AS $y)
        {
            $svg .= 'L'.$i.' '.$y;
            $i--;
        }
        $svg .= 'L0 50 Z" fill="url(#gradient)"/></svg>';
        // ---------- /SVG code ----------

        if (!empty($targetFile)){
            if (!$fh = @fopen($targetFile,'w')) throw new Exception(self::ERR_FILE_OPEN);
            if (!@fwrite($fh, $svg)) throw new Exception(self::ERR_FILE_WRITE);
            if (!@fclose($fh)) throw new Exception(self::ERR_FILE_CLOSE);
        }

        return $svg;

    }

    public function getChannels()
    {
        return $this->channels;
    }

    public function getSampleRate()
    {
        return $this->sampleRate;
    }

    public function getByteRate()
    {
        return $this->byteRate;
    }

    public function getKiloBitPerSecond()
    {
        return $this->kiloBitPerSecond;
    }

    public function getBitsPerSample()
    {
        return $this->bitsPerSample;
    }

    public function getTotalSamples()
    {
        return $this->totalSamples;
    }

    public function getTotalSeconds()
    {
        return $this->totalSeconds;
    }

}
