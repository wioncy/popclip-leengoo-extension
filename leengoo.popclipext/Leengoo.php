<?php

class Leengoo
{
    const LOGIN_URL = 'http://ru.leengoo.com/users/login';

    const TRANSLATE_URL = 'http://leengoo.com/synchronization/browsers/translate/%s/%s';

    const SAVE_WORD_URL = 'http://leengoo.com/synchronization/browsers/saveUsersWord/%s/?wordId=%s&translationId=%s&translate';

    const DICTIONARY_URL = 'http://leengoo.com/synchronization/browsers/collections';

    const COOKIES_FILE_NAME = 'Cookies.txt';

    const DEFAULT_TIME_ZONE = 'America/Los_Angeles';

    /** Curl object */
    protected $ch;
	
    public function __construct($user, $pass)
    {
        date_default_timezone_set(self::DEFAULT_TIME_ZONE);
        $this->ch = curl_init();
		
		//If cookies exists we are done. If not, let's login.
        if (file_exists(self::COOKIES_FILE_NAME))
        {
            $fileCreationDate = date('Y-m-d', filectime(self::COOKIES_FILE_NAME));
            $fileDate = new DateTime($fileCreationDate);
            if ( !(new DateTime)->diff($fileDate)->format('%m') )
                return;
        }
        $this->authenticateUser($user, $pass);
    }

	/**
	* Manually deletes curl object from memory
	*/
    public function __destruct()
    {
       curl_close($this->ch);
    }

    /**
     * Returns translated word
     * @param $word
     * @return mixed
     */
    public function getTranslation($word)
    {
        return json_decode(
            $this->executeRequest([
                CURLOPT_URL => sprintf(
                    self::TRANSLATE_URL,
                    $this->getDictionary(),
                    $word
                ),
            ]), true
        )['data'];
    }

    /**
     * Here we are adding our new word into dictionary
	 * @param string $wordId
	 * @param string $translationId
     */
    public function addToDictionary($wordId, $translationId)
    {
        return json_decode(
            $this->executeRequest([
                CURLOPT_URL => sprintf(
                    self::SAVE_WORD_URL,
                    $this->getDictionary(),
                    $wordId,
                    $translationId
                ),
            ]), true
        );
    }

    /**
     * User authorization
     */
    protected function authenticateUser($user, $pass)
    {
        if ($user && $pass)
        {
            $postParams = http_build_query([
                '_method' => 'POST',
                'data[User][email]' => $user,
                'data[User][password]' => $pass,
                'data[User][save]' => '1',
            ]);

            $this->executeRequest([
                CURLOPT_URL => self::LOGIN_URL,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postParams,
            ]);
        }
    }

    /**
     * Getting main dictionary indendificator
     * @return mixed
     */
    protected function getDictionary()
    {
        return json_decode(
            $this->executeRequest([
                CURLOPT_URL => self::DICTIONARY_URL,
            ]), true
        )[1]['collections'][0]['id'];
    }

    /**
     * Executing curl request
     * @param array $additionalOptions
     * @return mixed
     */
    protected function executeRequest(array $additionalOptions)
    {
        $cookiesPath = __DIR__ .  DIRECTORY_SEPARATOR . self::COOKIES_FILE_NAME;

        $options = array_replace([
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEFILE => $cookiesPath,
            CURLOPT_COOKIEJAR => $cookiesPath,
        ], $additionalOptions);

        curl_setopt_array($this->ch, $options);
        return curl_exec($this->ch);
    }
}

$word = getenv('POPCLIP_TEXT');
$user = getenv('POPCLIP_OPTION_EMAIL');
$pass = getenv('POPCLIP_OPTION_PASSWORD');

$client = new Leengoo($user, $pass);

$translationData = $client->getTranslation($word);

if ($translationData)
{
	$translationId = key($translationData['tls']);
	$translatedWord = current($translationData['tls']);
	$wordId = $translationData['id']['w'];
	$userWordId = $translationData['id']['uw'];

	//If not correct word we are going return 0
	if ($translatedWord == $word)
	{
		return;
	}
	
	//If it is new word for us, let it be recorded in our dictionary
	if (!$userWordId)
	{
	    $client->addToDictionary($wordId, $translationId);
	}	
	
	echo $translatedWord;
}