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
		
		return $this->generateHttpResponse(200, $this->view->render('home.html', [
			'domain' => $this->core->getHttpRequest()->getHost() . $subFolder
		]));
	}

	public function redirectOldBadge()
	{
		$repositorySlug = $this->route->getParam(0);

		return new HttpResponse(301, null, ['Location' => sprintf('/%s/master/badge.svg', $repositorySlug)]);
	}
	
	public function actionDisplayBadge()
	{
		$repositorySlug = $this->route->getParam(0);
		$branch = $this->route->getParam(1);

		if(($php7Support = $this->determinePHP7Support($repositorySlug, $branch)) === -2) {
			throw new HttpException(
				sprintf('Repository %s with branch %s does not exist on Travis-CI', $repositorySlug, $branch),
				404
			);
		}

		$text = 'unknown';
		
		if($php7Support === 0) {
			$text = 'supported';
		} elseif($php7Support > 0) {
			$text = 'unsupported';
		}
		
		$badgeFile = $this->core->getSiteRoot() . 'rsc' . DIRECTORY_SEPARATOR . 'badges' . DIRECTORY_SEPARATOR . 'php7-' . $text . '.svg';
		
		return new HttpResponse(200, $badgeFile, [
			'Content-Type' => 'image/svg+xml',
			'Content-Length' => filesize($badgeFile)
		], true);
	}

	/**
	 * @param string $repository
	 * @param string $branch
	 * @return int
	 */
	protected function determinePHP7Support($repository, $branch)
	{
		if(($content = $this->fetchApiData(sprintf('repos/%s/branches/%s', $repository, $branch))) === false)
			return -2;

		$json = json_decode($content, true);

		if(count($json) <= 0)
			return -2;

		$lastBuildId = $json['branch']['id'];

		if(($content = $this->fetchApiData(sprintf('repos/%s/builds/%s', $repository, $lastBuildId))) === false)
			return -2;

		$json = json_decode($content, true);

		$php7Support = -1;

		foreach($json['matrix'] as $job) {
			if(isset($job['config']['php']) === false || ($job['config']['php'] < 7.0 && $job['config']['php'] !== 'nightly'))
				continue;

			$php7Support = (int) $job['result'];
			break;
		}

		return $php7Support;
	}

	protected function fetchApiData($uri)
	{
		$curl = curl_init();

		curl_setopt_array($curl, [
			CURLOPT_URL => sprintf('https://api.travis-ci.org/%s', $uri),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_ENCODING => ''
		]);

		$content = curl_exec($curl);

		if(200 !== curl_getinfo($curl, CURLINFO_HTTP_CODE))
			return false;

		return $content;
	}
}

/* EOF */