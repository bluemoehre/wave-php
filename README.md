wave-php  [![Build Status](https://travis-ci.org/bluemoehre/wave-php.svg?branch=master)](https://travis-ci.org/bluemoehre/wave-php)
========

PHP class for native reading WAV (RIFF-WAVE) metadata and generating SVG-Waveforms. (PCM only)

Installation
------------

This class can easily be installed via [Composer](https://getcomposer.org):
```bash
composer require bluemoehre/wave-php
```

Alternatively you may include it the old fashioned way of downloading and adding it via:

```php
require 'Wave.php'
```

How to use
----------

Generate a single SVG:

  ```php
  use bluemoehre\Wave;

  // load WAV file
  $wave = new Wave('fooBar.wav');

  // generate SVG and save to file
  $wave->generateSvg('output.svg');

  ```

Generate multiple SVGs:

  ```php
  $files = array('foo.wav', 'bar.wav');
  $wave = new Wave();

  foreach ($files as $file){
    $wave->setFile($file);
    $wave->generateSvg(preg_replace('/\.wav$/', '.svg', $file);
  }

  ```

Methods
-------

- **generateSvg(** `string` $outputFile [, `float` $resolution ] **)** : `string`  
  Returns the waveform as SVG code. Optionally saves the output to the given filename.

- **getBitsPerSample()** : `integer`  
  Returns the bits per sample count

- **getByteRate()** : `integer`  
  Returns the audio byte rate

- **getChannels()** : `integer`  
  Returns the audio channel count

- **getKiloBitPerSecond()** : `float`  
  Returns the data rate of the audio

- **getSampleRate()** : `integer`  
  Returns the audio sample rate

- **getTotalSamples()** : `integer`  
  Returns the total audio sample count

- **getTotalSeconds(** `boolean` $float **)** : `integer` | `float`  
  Returns the audio length in seconds. Rounded by default - optinonally precise


TODO
--------
- add support for hi-res wave files
- find solution for styling waveform via CSS (maybe allow setup of a style path)
- move SVG code to external file (so everyone can modify the code meeting all needs)
- configurable vertical SVG detail
