wave-php
========

PHP class for native reading WAV (RIFF-WAVE) metadata and generating SVG-Waveforms. (PCM only)


TODO
--------
- find solution for styling waveform via CSS (maybe allow setup of a style path)
- move SVG code to external file (so everyone can modify the code meeting all needs)
- configurable vertical SVG detail


How to use
----------

Generate a single SVG:

  ```php
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
  
