<?php
/**
 * Selectel Storage Container PHP class
 *
 * PHP version 5
 *
 * @author   Eugene Smith <easmith@mail.ru>
 */
namespace Selectel;

class Container extends Storage
{
    /**
     * 'x-' Headers of container
     *
     * @var array
     */
    private $info;

    public function  __construct($url, $token = array(), $format = null, $info = array())
    {
        $this->url = $url . "/";
        $this->token = $token;
        $this->format = (!in_array($format, $this->formats, true) ? $this->format : $format);
        $this->info = (count($info) == 0 ? $this->getInfo(true) : $info);
    }

    /**
     * Getting container info
     *
     * @param boolean $refresh Refres? Default false
     *
     * @return array
     */
    public function getInfo($refresh = false)
    {
        if (!$refresh) return $this->info;

        $headers =	SCurl::init($this->url)
            ->setHeaders($this->token)
            ->request("HEAD")
            ->getHeaders();

        if (!in_array($headers["HTTP-Code"], array(204)))
            return $this->error($headers["HTTP-Code"], __METHOD__);

        return $this->info = $this->getX($headers);
    }

    /**
     * Getting file list
     *
     * @param int $limit Limit
     * @param string $marker Marker
     * @param string $prefix Prefix
     * @param string $path Path
     * @param string $delimiter Delemiter
     * @param string $format Format
     *
     * @return array|string
     */
    public function listFiles($limit = 10000, $marker = null, $prefix = null, $path = null, $delimiter = null, $format = null)
    {
        $params = array(
            'limit' => $limit,
            'marker' => $marker,
            'prefix' => $prefix,
            'path' => $path,
            'delimiter' => $delimiter,
            'format' => (!in_array($format, $this->formats, true) ? $this->format : $format)
        );

        $res = SCurl::init($this->url)
            ->setHeaders($this->token)
            ->setParams($params)
            ->request("GET")
            ->getContent();

        if ($params['format'] == '')
            return explode("\n", trim($res));

        return trim($res);
    }

    /**
     * Getting file with info and headers
     *
     * Supported headers:
     * If-Match
     * If-None-Match
     * If-Modified-Since
     * If-Unmodified-Since
     *
     * @param string $name
     * @param array $headers
     *
     * @return array
     */
    public function getFile($name, $headers = array())
    {
        $headers = array_merge($headers, $this->token);
        $res =	SCurl::init($this->url . $name)
            ->setHeaders($headers)
            ->request("GET")
            ->getResult();

        return $res;
    }

    /**
     * Getting file info
     *
     * @param string $name File name
     *
     * @return array
     */
    public function getFileInfo($name)
    {
        $res =	$this->listFiles(1, '', $name, null, null,  'json');
        $info = current(json_decode($res, true));
        return $this->format == 'json' ? json_encode($info) : $info;
    }

    /**
     * Upload local file
     *
     * @param string $localFileName The name of a local file
     * @param string $remoteFileName The name of storage file
     * @param array $header
     *
     * @return array
     */
    public function putFile($localFileName, $remoteFileName = null, $header = null)
    {
        if (is_null($remoteFileName))
            $remoteFileName = array_pop(explode(DIRECTORY_SEPARATOR, $localFileName));

        $header = is_array($header)?array_merge($header, $this->token):$this->token;

        $result = SCurl::init($this->url . $remoteFileName)
            ->setHeaders($header)
            ->putFile($localFileName)
            ->getResult();

        if (!in_array($result['info']["http_code"], array(201)))
            return $this->error($result['info']["http_code"], __METHOD__);

        return $result;
    }

    /**
     * Upload binary string as file
     *
     * @param string $contents
     * @param string|null $remoteFileName
     * @param array $header
     * @return array
     */
    public function putFileContents($contents, $remoteFileName = null, $header = null)
    {
        $header = is_array($header)?array_merge($header, $this->token):$this->token;

        $result = SCurl::init($this->url . $remoteFileName)
            ->setHeaders($header)
            ->putFileContents($contents)
            ->getResult();

        if (!in_array($result['info']["http_code"], array(201)))
            return $this->error($result['info']["http_code"], __METHOD__);

        return $result;
    }

    /**
     * Set meta info for file
     *
     * @param string $name File name
     * @param array $headers Headers
     *
     * @return integer
     */
    public function setFileHeaders($name, $headers)
    {
        $headers = $this->getX($headers, "X-Object-Meta-");
        if (get_class($this) != 'Selectel\Container') return 0;

        return $this->setMetaInfo($name, $headers);
    }

    /**
     * Creating directory
     *
     * @param string $name Directory name
     *
     * @return array
     */
    public function createDirectory($name)
    {
        $headers = array_merge(array("Content-Type: application/directory"), $this->token);
        $info =	SCurl::init($this->url . $name)
            ->setHeaders($headers)
            ->request("PUT")
            ->getInfo();

        return $info;
    }
}
