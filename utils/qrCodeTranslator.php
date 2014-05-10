<?php
class InvalidQRCodeException extends Exception {}

/**
 * QR Code Translator
 * 
 * Provides functions for translating a Hot Wheels QR code into a toy number.
 */
class QRCodeTranslator
{
	/**
	 * Get Toy Number From QR Code
	 *
	 * Takes a QR code and attempts to extract a toy number from it.
	 * 
	 * @param string $qrCodeData QR code.
	 * 
	 * @return string Toy number from QR code.
	 * @throws InvalidQRCodeException if there was an error translating the QR code.
	 */
	public static function getToyNumberFromQRCode($qrCodeData)
	{
		// make sure we the data we got is a URL
		if (!substr($qrCodeData, 0, 4) === 'http')
			throw new InvalidQRCodeException("QR code data is not a URL.\nQR code data:\n$qrCodeData");
		
		// hit that URL (not too hard though, just the headers)
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,            $qrCodeData);
		curl_setopt($ch, CURLOPT_TIMEOUT,        10);
		curl_setopt($ch, CURLOPT_HEADER,         true);
		curl_setopt($ch, CURLOPT_NOBODY,         true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		
		// execute cURL request
		$cURLResult = curl_exec($ch);
		
		// check for cURL errors
		$cURLErrorNum = curl_errno($ch);
		if ($cURLErrorNum !== 0)
			throw new Exception("cURL Error ($cURLErrorNum): " . curl_error($ch) . "\nQR code data:\n$qrCodeData");
		
		if ($cURLResult === false)
			throw new Exception("cURL Error: unknown\nQR code data:\n$qrCodeData");
		
		$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if ($statusCode !== 302)
		{
			$ex = new InvalidQRCodeException("QR code data URL did not return 302: Status code $statusCode\nQR code data:\n$qrCodeData");
			error_log($ex->getMessage());
			throw $ex;
		}
		
		
		// parse out the location header
		$index = strripos($cURLResult, 'location:');
		if ($index === false)
			throw new Exception("QR code data URL response headers missing location header. Headers:\n$cURLResult\nQR code data:\n$qrCodeData");
		
		$index += 9;
		
		$index2r = strpos($cURLResult, "\r", $index);
		$index2n = strpos($cURLResult, "\n", $index);
		
		$index2 = NULL;
		if ($index2r === false)
			$index2 = $index2n;
		else if ($index2n === false)
			$index2 = $index2r;
		else
			$index2 = min($index2r, $index2n);
		
		if ($index2 === false)
			throw new Exception("Error parsing QR code data URL response. Unable to find end of location header. Headers:\n$cURLResult\nQR code data:\n$qrCodeData");
		
		$locationValue = substr($cURLResult, $index, $index2 - $index);
		
		
		// parse the toy number from the location value
		$index = strrpos($locationValue, '/vid/');
		if ($index === false)
		{
			$ex = new InvalidQRCodeException("Error parsing QR code data URL response. \"/vid/\" missing from the location header. Location header value:\n$locationValue\nHeaders:\n$cURLResult\nQR code data:\n$qrCodeData");
			error_log($ex->getMessage());
			throw $ex;
		}
		
		$index += 5;
		
		$index2 = strpos($locationValue, '?', $index);
		if ($index2 === false)
			$index2 = strlen($locationValue);
		
		$toyNumber = rtrim(substr($locationValue, $index, $index2 - $index));
		
		if (strlen($toyNumber) === 0)
			throw new Exception("Error parsing QR code data URL response. Toy number in location header is empty. Location header value:\n$locationValue\nHeaders:\n$cURLResult\nQR code data:\n$qrCodeData");
		
		return $toyNumber;
	}
}
?>