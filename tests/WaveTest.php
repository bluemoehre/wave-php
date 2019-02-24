<?php

use PHPUnit\Framework\TestCase ;
use bluemoehre\Wave;

class WaveTest extends TestCase
{
  public function test()
  {
    $wave = new Wave('fixtures/20-20000Hz.wav');
    $this->assertEquals(1, $wave->getChannels(), 'Channel count should match');
    $this->assertEquals(44100, $wave->getSampleRate(), 'Sample rate should match');
    $this->assertEquals(88200, $wave->getByteRate(), 'Byte rate should match');
    $this->assertEquals(705.6, $wave->getKiloBitPerSecond(), 'Kilobit per second should match');
    $this->assertEquals(16, $wave->getBitsPerSample(), 'Bits per sample should match');
    $this->assertEquals(441000, $wave->getTotalSamples(), 'Total samples should match');
    $this->assertEquals(10, $wave->getTotalSeconds(), 'Total seconds should match');
    $this->assertEquals(10.0, $wave->getTotalSeconds(true), 'Total seconds with decimals should match');
    $this->assertEquals(file_get_contents('./tests/snapshots/20-20000Hz.svg'), $wave->generateSvg(), 'SVG should match snapshot');
  }
}