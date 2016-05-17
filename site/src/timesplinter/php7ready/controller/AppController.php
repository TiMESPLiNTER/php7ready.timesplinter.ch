<?php

namespace timesplinter\php7ready\controller;

use ch\timesplinter\controller\PageController;
use ch\timesplinter\core\HttpException;
use ch\timesplinter\core\HttpResponse;
use timesplinter\tsfw\common\StringUtils;

/**
 * @author Pascal Muenst <dev@timesplinter.ch>
 * @copyright Copyright (c) 2015, TiMESPLiNTER Webdevelopment
 */
class AppController extends PageController
{
	public function getPage()
	{
		$subFolder = StringUtils::afterFirst(getcwd(), $_SERVER['DOCUMENT_ROOT']);
		
		return $this->generateHttpResponse(200, $this->view->render('home.html', array(
			'domain' => $this->core->getHttpRequest()->getHost() . $subFolder
		)));
	}
	
	public function actionDisplayBadge()
	{
		if(($php7Support = $this->determinePHP7Support($this->route->getParam(0))) === -2)
			throw new HttpException('Repository does not exist on Travis-CI', 404);

		$text = 'unknown';
		
		if($php7Support === 0) {
			$text = 'supported';
		} elseif($php7Support > 0) {
			$text = 'unsupported';
		}
		
		$badgeFile = $this->core->getSiteRoot() . 'rsc' . DIRECTORY_SEPARATOR . 'badges' . DIRECTORY_SEPARATOR . 'php7-' . $text . '.svg';
		
		return new HttpResponse(200, $badgeFile, array(
			'Content-Type' => 'image/svg+xml',
			'Content-Length' => filesize($badgeFile)
		), true);
	}

	/**
	 * @param string $repository
	 *
	 * @return int
	 */
	protected function determinePHP7Support($repository)
	{
		if(($content = $this->fetchApiData(sprintf('https://api.travis-ci.org/repositories/%s/builds.json', $repository))) === false)
			return -2;

		$json = json_decode($content, true);

		if(count($json) <= 0)
			return -2;

		$lastBuildId = $json[0]['id'];

		if(($content = $this->fetchApiData(sprintf('https://api.travis-ci.org/builds/%s', $lastBuildId))) === false)
			return -2;

		$json = json_decode($content, true);

		$php7Support = -1;

		foreach($json['matrix'] as $job) {
			if(isset($job['config']['php']) === false || ($job['config']['php'] < 7.0 && $job['config']['php'] !== 'nightly'))
				continue;

			$php7Support = (int)$job['result'];
			break;
		}

		return $php7Support;
	}

	protected function fetchApiData($url)
	{
		$curl = curl_init();

		curl_setopt_array($curl, array(
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_ENCODING => ''
		));

		$content = curl_exec($curl);

		if(200 !== curl_getinfo($curl, CURLINFO_HTTP_CODE))
			return false;

		return $content;
	}
}

/* EOF */