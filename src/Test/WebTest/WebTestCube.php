<?php

namespace CubeTools\CubeCommonDevelop\Test\WebTest;

use CubeTools\CubeCommonDevelop\Test\MoreAssertionsTrait;
use Symfony\Component\BrowserKit\Client;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Form;

class WebTestCube extends WebTestBase
{
    use MoreAssertionsTrait;

    /**
     * Do a request and check the http status.
     *
     * @param Client $client
     * @param string $method   http method GET|POST|...
     * @param string $path     path relative to root url
     * @param bool   $redirect (see in checkResponse)
     *
     * @return Crawler
     */
    public function requestSuccessful(Client $client, $method, $path, $redirect = false)
    {
        $client->request($method, $path);

        return $this->checkResponse($client, $redirect);
    }

    /**
     * Submit a form and check the http reply.
     *
     * @param Client $client
     * @param Form   $form     The form to submit
     * @param bool   $redirect (see in checkResponse)
     *
     * @return Crawler
     */
    public function submitSuccessful(Client $client, $form, $redirect)
    {
        $client->submit($form);

        return $this->checkResponse($client, $redirect);
    }

    /**
     * Like submitSuccesful, but shows form errors, which is useful to debug form problems.
     *
     * @param Client $client
     * @param Form   $form
     * @param bool   $redirect
     *
     * @return Crawler
     */
    public function submitSuccessfulDebugForm(Client $client, $form, $redirect)
    {
        $ex = null;
        $profile = null;
        $this->enableProfiler($client);
        try {
            $client->submit($form);
            if ($redirect && $client->getResponse()->isRedirect()) {
                $profile = $client->getProfile();
            }
            $r = $this->checkResponse($client, $redirect);
        } catch (\Exception $e) {
            $ex = $e;
        } catch (\Throwable $e) {
            $ex = $e;
        }
        if (null === $profile) {
            $profile = $client->getProfile();
        }
        if (false === $profile) {
            echo '    profiler not enabled    ';
        } else {
            $formErrs = $this->getFormErrors($profile);
            if ($formErrs) {
                $msg = sprintf('form errors: "%s"', join('", "', $formErrs));
                if ($ex) {
                    $exClass = get_class($ex);
                    throw new $exClass($ex->getMessage().'    '.$msg, $ex->getCode(), $ex);
                }
                echo "\n    ".$msg."\n";
            } else {
                echo '    no form errors    ';
            }
        }
        if ($ex) {
            throw $ex;
        }

        return $r;
    }

    /**
     * Click on a link and check the http reply.
     *
     * @param Client                             $client
     * @param \Symfony\Component\DomCrawler\Link $link     The link to follow
     * @param bool                               $redirect (see in checkResponse)
     *
     * @return Crawler
     */
    public function clickSuccessful(Client $client, $link, $redirect = false)
    {
        $client->click($link);

        return $this->checkResponse($client, $redirect);
    }

    /**
     * @param Client $client
     * @param bool   $redirect if to follow a redirect
     *
     * @return Crawler
     */
    public function checkResponse(Client $client, $redirect)
    {
        if ($redirect) {
            $resp = $client->getResponse();
            if ($resp->isRedirect()) {
                // pass
                $crawler = $client->followRedirect();
            } elseif ($resp->isSuccessful()) {
                $this->fail('expected redirect missing');
            } else {
                // error, will be handled below
                $crawler = null;
            }
        } else {
            $crawler = $client->getCrawler();
        }
        $resp = $client->getResponse();
        if ($resp->isSuccessful()) {
            $this->assertTrue(true);

            return $crawler;
        }
        if ($resp->isRedirect()) {
            $msg = static::msgUnexpectedRedirect($client);
        } else {
            $msg = 'http status: '.self::getPageLoadingFailure($client->getCrawler(), $this->getName());
        }
        $this->assertSame(200, $resp->getStatusCode(), $msg);
    }

    /**
     * checks if the page loads valid and if it contains a matching heading.
     *
     * @param Client $client
     * @param string $url    url to page to check
     * @param string $title  expected title on page
     *
     * @return Crawler crawler of the full page
     */
    public function basicPageCheck(Client $client, $url, $title)
    {
        $crawler = $this->requestSuccessful($client, 'GET', $url);
        $this->assertGreaterThan(
            0,
            $crawler->filter('h1, h2, h3, h4')->filter(":contains('$title')")->count(),
            'page has matching title'
        );

        return $crawler;
    }

    /**
     * checks if there is a link for all given urls.
     *
     * @param Crawler $crawler
     * @param array   $urls    urls (string) to find in the page
     * @param string  $inSpace string for $crawler->filter() to get the search range
     */
    public function containsUrls($crawler, array $urls, $inSpace = '#mainSpace')
    {
        if ($inSpace) {
            $searchCrawler = $crawler->filter($inSpace);
        } else {
            $searchCrawler = $crawler;
        }

        foreach ($urls as $url) {
            $this->assertGreaterThan(0, $searchCrawler->filter('a[href$="'.$url.'"]')->count(), 'link to '.$url);
        }
    }

    /**
     * Checks if all matching links are working.
     *
     * @param Client $client
     * @param string $selector selector for links, like 'a[href*="?sort="]' or simply 'a'
     * @param int    $minLinks minimum number of links to find
     *
     * @return \Symfony\Component\DomCrawler\Link[] tested links
     */
    public function checkLinksLoadable($client, $selector, $minLinks = 1)
    {
        $i = 0;
        $checkLinks = $client->getCrawler()->filter($selector)->links();
        foreach ($checkLinks as $link) {
            $this->prepareNextRequest();
            $this->clickSuccessful($client, $link);
            ++$i;
        }
        $this->assertGreaterThanOrEqual($minLinks, $i, 'number of checked links');

        return $checkLinks;
    }

    /**
     * Fills all empty Form fields with some value.
     *
     * @param Form $form
     *
     * @return Form
     */
    public function fillForm($form)
    {
        $i = 0;
        foreach ($form->all() as $element) {
            if (!$element->getValue()) {
                // is empty
                $element->setValue($this->getElementValueToFillIn($element, $i));
            }
            ++$i;
        }

        return $form;
    }

    /**
     * Get errors of forms from the profile.
     *
     * @param \Symfony\Component\HttpKernel\Profiler\Profile $profile
     *
     * @return string[]|null all form errors with the keys containing the name of the form and the child
     */
    public static function getFormErrors($profile)
    {
        $formsData = $profile->getCollector('form')->getData();
        if (empty($formsData['nb_errors'])) {
            return null;
        }

        $errors = array();
        foreach ($formsData['forms'] as $formName => $form) {
            self::collectFormErrors($errors, $form, array($formName));
        }

        if (count($errors) !== $formsData['nb_errors']) {
            $errors['TEST-'.__FUNCTION__] = 'DEBUG TEST ERROR: expected '.$formsData['nb_errors'].' errors, but found '.count($errors);
        }

        return $errors;
    }

    /**
     * Get a value for a form field.
     *
     * @param \Symfony\Component\DomCrawler\Field\FormField $element form element to get the value for
     * @param int                                          $i       index
     *
     * @return mixed value to fill in
     */
    protected function getElementValueToFillIn($element, $i)
    {
        $eClass = get_class($element);
        if (false !== strpos($eClass, 'ChoiceFormField')) {
            $options = $element->availableOptionValues();
            if (array() === $options) {
                $this->markTestSkipped('no option values for form field '.$element->getName());
            }
            $j = $i % count($options);
            $val = $options[$j];
            if ($val) {
                // fine
            } elseif (isset($options[$j + 1])) {
                $val = $options[$j + 1];
            } elseif (isset($options[$j - 1])) {
                $val = $options[$j - 1];
            } else {
                $this->markTestSkipped('no NON-empty option value for form field '.$element->getName());
            }
        } else {
            $eName = $element->getName();
            $val = "X_$i-$eName,y";
        }

        return $val;
    }

    /**
     * Returns the logged in user.
     *
     * @param Client $client current test client
     *
     * @return \Symfony\Component\Security\Core\User\UserInterface user object of logged in user
     *
     * @throws Exception when no user is logged in (probably because no request was done before)
     */
    protected static function getThisUser(Client $client)
    {
        $token = null;
        if (null !== $client->getContainer()) {
            $token = $client->getContainer()->get('security.token_storage')->getToken();
        }
        if (null === $token) {
            throw new \Exception(__METHOD__.' only works after a request in this test');
            //$client->getKernel()->boot() does not load user data
        }

        return $token->getUser();
    }

    /**
     * Call $client->enableProfiler(), with checking kernel first.
     *
     * Because the error message is irritating when kernel is not ready.
     *
     * @param Client $client
     */
    protected static function enableProfiler($client)
    {
        if (!$client->getContainer()) {
            $client->getKernel()->boot(); // $client->enableProfiler needs a working container
        }
        $client->enableProfiler();
    }

    private static function collectFormErrors(array &$errorsResult, $form, array $formNames)
    {
        if (isset($form['errors']) && count($form['errors'])) {
            $value = '';
            foreach ($form['errors'] as $item) {
                $value .= '; '.$item['message'];
            }
            $key = join(':', $formNames);
            $errorsResult[$key] = $key." = '".substr($value, 2)."'";
        }
        foreach ($form['children'] as $childName => $child) {
            self::collectFormErrors($errorsResult, $child, array_merge($formNames, array($childName)));
        }
    }
}
