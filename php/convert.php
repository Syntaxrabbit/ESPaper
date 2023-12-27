<?php

// define your ESP's hostname
$esphost = "192.168.X.X";

// read image to create black and white or red and white binary array from
$imagick = new Imagick("source.jpg");

// read palette image file
$replacement = new Imagick("black-white-red.gif");

// slightly increase contrast and brightness for "cleaner" dithering look
$imagick->brightnessContrastImage(5, 10);

// apply palette to source image and dither with Floyd-Steinberg algorithm
$imagick->remapImage($replacement, imagick::DITHERMETHOD_FLOYDSTEINBERG);

// set width and height of epaper display
$width = 800;;
$height = 480;

// default all pixels white
$binarydataBW = array_fill(0, $width * $height, false);
$binarydataRW = array_fill(0, $width * $height, false);

// iterate all epaper's pixels
for($y = 0; $y < $height; $y++) {
	for($x = 0; $x < $width; $x++) {
		$binaryindex = ($y * $width) + $x;
		$color = $imagick->getImagePixelColor($x, $y);

		if($color->getColorAsString() == "srgb(0,0,0)")
		{
			// pixel is black, so set value in black and white array to true
			$binarydataBW[$binaryindex] = true;
		}
		else if($color->getColorAsString() == "srgb(255,0,0)")
		{
			// pixel is red, so set value in red and white array to true
			$binarydataRW[$binaryindex] = true;
		}
	}
} 

// create byte array for later HTTP requests
$bytedataBW = array_fill(0, $width * $height / 4, 0);
$bytedataRW = array_fill(0, $width * $height / 4, 0);

// create byte array from binary array
for($index = 0; $index < $width * $height / 4; $index++)
{
	// epaper firmware from waveshare expects inversed blocks of four bits
	$byteBW = 15;
	$byteRW = 15;
	
	// calculate byte value and assign to temporary variable
	for($i = 0; $i < 4 ; $i++) {
		if($binarydataBW[3 + ($index * 4) - $i]) $byteBW -= pow(2, $i);
		if($binarydataRW[3 + ($index * 4) - $i]) $byteRW -= pow(2, $i);
	}

	// shift four bit block to right if index is even, otherwise to left
	$shift = $index % 2 == 0 ? 1 : -1;
	$bytedataBW[$index + $shift] = $byteBW;
	$bytedataRW[$index + $shift] = $byteRW;
}

$curlInit = curl_init();
curl_setopt($curlInit, CURLOPT_CONNECTTIMEOUT, 3); 
curl_setopt($curlInit, CURLOPT_TIMEOUT, 3);

// start transfer to waveshare firmware's HTTP server
curl_setopt($curlInit, CURLOPT_URL, "http://" . $esphost . "/EPDx_");
curl_exec($curlInit);

// iterate byte arrays
for($index = 0; $index < $width * $height / 4000; $index++)
{
	$data = "";

	for($j = 0; $j < 1000; $j++)
	{
		// append decimal byte value of four bit block to char 'a' which is decimal 97
		$byte = $bytedataBW[$j + ($index * 1000)];
		$data .= chr($byte + 97);
	}

	// send 1000 chars to waveshare firmware's HTTP server
	curl_setopt($curlInit, CURLOPT_URL, "http://" . $esphost . "/" . $data . "iodaLOAD_");
	curl_exec($curlInit);
}

	// switch waveshare firmware to next color channel which is red
	curl_setopt($curlInit, CURLOPT_URL, "http://" . $esphost . "/NEXT_");
        curl_exec($curlInit);

for($index = 0; $index < $width * $height / 4000; $index++)
{       
	$data = "";

	for($j = 0; $j < 1000; $j++)
	{       
		$byte = $bytedataRW[$j + ($index * 1000)];
		$data .= chr($byte + 97);
	}

	curl_setopt($curlInit, CURLOPT_URL, "http://" . $esphost . "/" . $data . "iodaLOAD_");
	curl_exec($curlInit);
}

// finish transfer and show image
curl_setopt($curlInit, CURLOPT_URL, "http://" . $esphost . "/SHOW_");
curl_exec($curlInit);
$debug = false;

// output dithered black and red images to disk
if($debug)
{
	$blackresult = new Imagick();
	$redresult = new Imagick();
	$blackresult->newImage(800, 480, new ImagickPixel('white'));
	$redresult->newImage(800, 480, new ImagickPixel('white'));
	$iteratorblack = $blackresult->getPixelIterator();
	$iteratorred = $redresult->getPixelIterator();
	for($i = 0; $i < count($binarydataBW); $i++)
	{
		if($binarydataBW[$i])
		{	
			$iteratorblack->setIteratorRow(floor($i / 800));
			$row = $iteratorblack->getCurrentIteratorRow();
			$pixel = $row[$i % 800];
			$pixel->setColor('#000000');
			$iteratorblack->syncIterator();
		}
		if($binarydataRW[$i])
		{       
			$iteratorred->setIteratorRow(floor($i / 800));
			$row = $iteratorred->getCurrentIteratorRow();
			$pixel = $row[$i % 800];
			$pixel->setColor('#FF0000');
			$iteratorred->syncIterator();
		}
	}
	$blackresult->writeImage("black.png");
	$redresult->writeImage("red.png");
}
?>
