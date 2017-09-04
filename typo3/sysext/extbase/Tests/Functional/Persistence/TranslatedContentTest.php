<?php
declare(strict_types=1);

namespace TYPO3\CMS\Extbase\Tests\Functional\Persistence;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\Response;

/**
 * This test is an Extbase version of the \TYPO3\CMS\Frontend\Tests\Functional\Rendering\LocalizedContentRenderingTest
 * scenarios are the same, just a way of fetching content is different
 *
 * This test documents current behaviour of extbase which is inconsistent with TypoScript rendering of tt_content.
 */
class TranslatedContentTest extends \TYPO3\CMS\Core\Tests\Functional\DataHandling\AbstractDataHandlerActionTestCase
{
    const VALUE_PageId = 89;
    const TABLE_Content = 'tt_content';
    const TABLE_Pages = 'pages';

    /**
     * @var string
     */
    protected $scenarioDataSetDirectory = 'typo3/sysext/frontend/Tests/Functional/Rendering/DataSet/';

    /**
     * @var array
     */
    protected $testExtensionsToLoad = ['typo3/sysext/extbase/Tests/Functional/Fixtures/Extensions/blog_example'];

    /**
     * @var array
     */
    protected $coreExtensionsToLoad = ['extbase', 'fluid'];

    /**
     * @var \TYPO3\CMS\Extbase\Object\ObjectManagerInterface The object manager
     */
    protected $objectManager;

    /**
     * @var \ExtbaseTeam\BlogExample\Domain\Repository\TtContentRepository
     */
    protected $contentRepository;

    /**
     * Custom 404 handler returning valid json is registered so the $this->getFrontendResponse()
     * does not fail on 404 pages
     *
     * @var array
     */
    protected $configurationToUseInTestInstance = [
        'FE' => [
            'pageNotFound_handling' => 'READFILE:typo3/sysext/frontend/Tests/Functional/Rendering/DataSet/404Template.html'
        ]
    ];

    /**
     * @var array
     */
    protected $pathsToLinkInTestInstance = [
        'typo3/sysext/core/Tests/Functional/Fixtures/Frontend/AdditionalConfiguration.php' => 'typo3conf/AdditionalConfiguration.php',
        'typo3/sysext/frontend/Tests/Functional/Fixtures/Images' => 'fileadmin/user_upload'
    ];

    protected function setUp()
    {
        parent::setUp();
        $this->importScenarioDataSet('LiveDefaultPages');
        $this->importScenarioDataSet('LiveDefaultElements');

        $this->backendUser->workspace = 0;
        $this->objectManager = GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Object\ObjectManager::class);
        $this->contentRepository = $this->objectManager->get(\ExtbaseTeam\BlogExample\Domain\Repository\TtContentRepository::class);
        $this->setUpFrontendRootPage(1, [
            'typo3/sysext/extbase/Tests/Functional/Fixtures/Extensions/blog_example/Configuration/TypoScript/setup.txt',
            'typo3/sysext/extbase/Tests/Functional/Persistence/Fixtures/Frontend/ContentJsonRenderer.ts'

        ]);
    }

    public function defaultLanguageConfigurationDataProvider(): array
    {
        return [
            [
                'typoScript' => 'config.sys_language_overlay = 0
                                config.sys_language_mode =',
            ],
            [
                'typoScript' => 'config.sys_language_overlay = 0
                                config.sys_language_mode = content_fallback',
            ],
            [
                'typoScript' => 'config.sys_language_overlay = 0
                                config.sys_language_mode = content_fallback;1,0',
            ],
            [
                'typoScript' => 'config.sys_language_overlay = 0
                                config.sys_language_mode = strict',
            ],
            [
                'typoScript' => 'config.sys_language_overlay = 0
                                config.sys_language_mode = ignore',
            ],
            [
                'typoScript' => 'config.sys_language_overlay = 1
                                config.sys_language_mode =',
            ],
            [
                'typoScript' => 'config.sys_language_overlay = 1
                                config.sys_language_mode = content_fallback',
            ],
            [
                'typoScript' => 'config.sys_language_overlay = 1
                                config.sys_language_mode = content_fallback;1,0',
            ],
            [
                'typoScript' => 'config.sys_language_overlay = 1
                                config.sys_language_mode = strict',
            ],
            [
                'typoScript' => 'config.sys_language_overlay = 1
                                config.sys_language_mode = ignore',
            ],
            [
                'typoScript' => 'config.sys_language_overlay = hideNonTranslated
                                config.sys_language_mode =',
            ],
            [
                'typoScript' => 'config.sys_language_overlay = hideNonTranslated
                                config.sys_language_mode = content_fallback',
            ],
            [
                'typoScript' => 'config.sys_language_overlay = hideNonTranslated
                                config.sys_language_mode = content_fallback;1,0',
            ],
            [
                'typoScript' => 'config.sys_language_overlay = hideNonTranslated
                                config.sys_language_mode = strict',
            ],
            [
                'typoScript' => 'config.sys_language_overlay = hideNonTranslated
                                config.sys_language_mode = ignore',
            ],
        ];
    }

    /**
     * For the default language all combination of language settings should give the same result,
     * regardless of TypoScript settings, if the requested language is "0" then no TypoScript settings apply.
     *
     * @test
     * @dataProvider defaultLanguageConfigurationDataProvider
     *
     * @param string $typoScript
     */
    public function onlyEnglishContentIsRenderedForDefaultLanguage(string $typoScript)
    {
        $this->addTypoScriptToTemplateRecord(1, $typoScript);

        $frontendResponse = $this->getFrontendResponse(self::VALUE_PageId, 0);
        $responseSections = $frontendResponse->getResponseSections('Extbase:list()');
        $visibleHeaders = ['Regular Element #1', 'Regular Element #2', 'Regular Element #3'];
        $this->assertThat(
            $responseSections,
            $this->getRequestSectionHasRecordConstraint()
            ->setTable(self::TABLE_Content)
            ->setField('header')
            ->setValues(...$visibleHeaders)
        );
        $this->assertThat(
            $responseSections,
            $this->getRequestSectionDoesNotHaveRecordConstraint()
            ->setTable(self::TABLE_Content)
            ->setField('header')
            ->setValues(...$this->getNonVisibleHeaders($visibleHeaders))
        );
    }

    /**
     * Dutch language has pages_language_overlay record and some content elements are translated
     *
     * @return array
     */
    public function dutchDataProvider(): array
    {
        //Expected behaviour:
        //Page is translated to Dutch, so changing sys_language_mode does NOT change the results
        //Page title is always [DK]Page, and both sys_language_content and sys_language_uid are always 1
        return [
            [
                'typoScript' => 'config.sys_language_overlay = 0
                                config.sys_language_mode =',
                'visibleRecordHeaders' => ['[Translate to Dansk:] Regular Element #1', '[Translate to Dansk:] Regular Element #3', '[DK] Without default language', 'Regular Element #2', '[DK] UnHidden Element #4'],
            ],
            [
                'typoScript' => 'config.sys_language_overlay = 0
                                config.sys_language_mode = content_fallback',
                'visibleRecordHeaders' => ['[Translate to Dansk:] Regular Element #1', '[Translate to Dansk:] Regular Element #3', '[DK] Without default language', 'Regular Element #2', '[DK] UnHidden Element #4'],
            ],
            [
                'typoScript' => 'config.sys_language_overlay = 0
                                config.sys_language_mode = content_fallback;1,0',
                'visibleRecordHeaders' => ['[Translate to Dansk:] Regular Element #1', '[Translate to Dansk:] Regular Element #3', '[DK] Without default language', 'Regular Element #2', '[DK] UnHidden Element #4'],
            ],
            [
                'typoScript' => 'config.sys_language_overlay = 0
                                config.sys_language_mode = strict',
                'visibleRecordHeaders' => ['[Translate to Dansk:] Regular Element #1', '[Translate to Dansk:] Regular Element #3', '[DK] Without default language'],
            ],
            [
                'typoScript' => 'config.sys_language_overlay = 0
                                config.sys_language_mode = ignore',
                'visibleRecordHeaders' => ['[Translate to Dansk:] Regular Element #1', '[Translate to Dansk:] Regular Element #3', '[DK] Without default language', 'Regular Element #2', '[DK] UnHidden Element #4'],
            ],
            5 => [
                'typoScript' => 'config.sys_language_overlay = 1
                                config.sys_language_mode =',
                'visibleRecordHeaders' => ['[Translate to Dansk:] Regular Element #1', 'Regular Element #2', '[Translate to Dansk:] Regular Element #3', '[DK] Without default language', '[DK] UnHidden Element #4'],
            ],
//             Expected behaviour:
//             Not translated element #2 is shown because sys_language_overlay = 1 (with sys_language_overlay = hideNonTranslated, it would be hidden)
            [
                'typoScript' => 'config.sys_language_overlay = 1
                                config.sys_language_mode = content_fallback',
                'visibleRecordHeaders' => ['[Translate to Dansk:] Regular Element #1', 'Regular Element #2', '[Translate to Dansk:] Regular Element #3', '[DK] Without default language', '[DK] UnHidden Element #4'],
            ],
//             Expected behaviour:
//             Same as config.sys_language_mode = content_fallback because we're requesting language 1, so no additional fallback possible

            [
                'typoScript' => 'config.sys_language_overlay = 1
                                config.sys_language_mode = content_fallback;1,0',
                'visibleRecordHeaders' => ['[Translate to Dansk:] Regular Element #1', 'Regular Element #2', '[Translate to Dansk:] Regular Element #3', '[DK] Without default language', '[DK] UnHidden Element #4'],
            ],
            [
                'typoScript' => 'config.sys_language_overlay = 1
                                config.sys_language_mode = strict',
                'visibleRecordHeaders' => ['[Translate to Dansk:] Regular Element #1', '[Translate to Dansk:] Regular Element #3', '[DK] Without default language'],
            ],
            [
                'typoScript' => 'config.sys_language_overlay = 1
                                config.sys_language_mode = ignore',
                'visibleRecordHeaders' => ['[Translate to Dansk:] Regular Element #1', 'Regular Element #2', '[Translate to Dansk:] Regular Element #3', '[DK] Without default language', '[DK] UnHidden Element #4'],
            ],
//             Expected behaviour:
//             Non translated default language elements are not shown, because of hideNonTranslated
            10 => [
                'typoScript' => 'config.sys_language_overlay = hideNonTranslated
                                config.sys_language_mode =',
                'visibleRecordHeaders' => ['[Translate to Dansk:] Regular Element #1', 'Regular Element #2', '[Translate to Dansk:] Regular Element #3', '[DK] Without default language', '[DK] UnHidden Element #4'],
            ],
            [
                'typoScript' => 'config.sys_language_overlay = hideNonTranslated
                                config.sys_language_mode = content_fallback',
                'visibleRecordHeaders' => ['[Translate to Dansk:] Regular Element #1', 'Regular Element #2', '[Translate to Dansk:] Regular Element #3', '[DK] Without default language', '[DK] UnHidden Element #4'],
            ],
            [
                'typoScript' => 'config.sys_language_overlay = hideNonTranslated
                                config.sys_language_mode = content_fallback;1,0',
                'visibleRecordHeaders' => ['[Translate to Dansk:] Regular Element #1', 'Regular Element #2', '[Translate to Dansk:] Regular Element #3', '[DK] Without default language', '[DK] UnHidden Element #4'],
            ],
//            Setting sys_language_mode = strict has the same effect as previous data sets, because the translation of the page exists
            [
                'typoScript' => 'config.sys_language_overlay = hideNonTranslated
                                config.sys_language_mode = strict',
                'visibleRecordHeaders' => ['[Translate to Dansk:] Regular Element #1', '[Translate to Dansk:] Regular Element #3', '[DK] Without default language'],
            ],
            [
                'typoScript' => 'config.sys_language_overlay = hideNonTranslated
                                config.sys_language_mode = ignore',
                'visibleRecordHeaders' => ['[Translate to Dansk:] Regular Element #1', 'Regular Element #2', '[Translate to Dansk:] Regular Element #3', '[DK] Without default language', '[DK] UnHidden Element #4'],
            ],
        ];
    }

    /**
     * @test
     * @dataProvider dutchDataProvider
     *
     * @param string $typoScript
     * @param array $visibleHeaders
     */
    public function renderingOfDutchLanguage(string $typoScript, array $visibleHeaders)
    {
        $this->addTypoScriptToTemplateRecord(1, $typoScript);
        $frontendResponse = $this->getFrontendResponse(self::VALUE_PageId, 1);
        $responseSections = $frontendResponse->getResponseSections('Extbase:list()');
        $this->assertThat(
            $responseSections,
            $this->getRequestSectionHasRecordConstraint()
            ->setTable(self::TABLE_Content)
            ->setField('header')
            ->setValues(...$visibleHeaders)
        );
        $this->assertThat(
            $responseSections,
            $this->getRequestSectionDoesNotHaveRecordConstraint()
            ->setTable(self::TABLE_Content)
            ->setField('header')
            ->setValues(...$this->getNonVisibleHeaders($visibleHeaders))
        );
    }

    public function contentOnNonTranslatedPageDataProvider(): array
    {
        //Expected behaviour:
        //the page is NOT translated so setting sys_language_mode to different values changes the results
        //- setting sys_language_mode to empty value makes TYPO3 return default language records
        //- setting it to strict throws 404, independently from other settings
        //Setting config.sys_language_overlay = 0
        return [
            [
                'typoScript' => 'config.sys_language_overlay = 0
                                config.sys_language_mode =',
                'visibleRecordHeaders' => ['Regular Element #1', 'Regular Element #2', 'Regular Element #3'],
            ],
            [
                'typoScript' => 'config.sys_language_overlay = 0
                                config.sys_language_mode = content_fallback',
                'visibleRecordHeaders' => ['Regular Element #1', 'Regular Element #2', 'Regular Element #3'],
            ],
            [
                'typoScript' => 'config.sys_language_overlay = 0
                                config.sys_language_mode = content_fallback;1,0',
                'visibleRecordHeaders' => ['[Translate to Dansk:] Regular Element #1', 'Regular Element #2', '[Translate to Dansk:] Regular Element #3', '[DK] UnHidden Element #4', '[DK] Without default language'],
            ],
            [
                'typoScript' => 'config.sys_language_overlay = 0
                                config.sys_language_mode = strict',
                'visibleRecordHeaders' => [],
                'status' => 404,
            ],
            [
                'typoScript' => 'config.sys_language_overlay = 0
                                config.sys_language_mode = ignore',
                'visibleRecordHeaders' => ['[Translate to Deutsch:] [Translate to Dansk:] Regular Element #1', 'Regular Element #2', 'Regular Element #3', '[DE] Without default language'],
            ],
            5 => [
                'typoScript' => 'config.sys_language_overlay = 1
                                config.sys_language_mode =',
                'visibleRecordHeaders' => ['Regular Element #1', 'Regular Element #2', 'Regular Element #3'],
            ],
            //falling back to default language
            [
                'typoScript' => 'config.sys_language_overlay = 1
                                config.sys_language_mode = content_fallback',
                'visibleRecordHeaders' => ['Regular Element #1', 'Regular Element #2', 'Regular Element #3'],
            ],
            //Dutch elements are shown because of the content fallback 1,0 - first Dutch, then default language
            //note that '[DK] Without default language' is NOT shown - due to overlays (fetch default language and overlay it with translations)
            [
                'typoScript' => 'config.sys_language_overlay = 1
                                config.sys_language_mode = content_fallback;1,0',
                'visibleRecordHeaders' => ['[Translate to Dansk:] Regular Element #1', 'Regular Element #2', '[Translate to Dansk:] Regular Element #3', '[DK] Without default language', '[DK] UnHidden Element #4'],
            ],
            [
                'typoScript' => 'config.sys_language_overlay = 1
                                config.sys_language_mode = strict',
                'visibleRecordHeaders' => [],
                'status' => 404
            ],
            [
                'typoScript' => 'config.sys_language_overlay = 1
                                config.sys_language_mode = ignore',
                'visibleRecordHeaders' => ['[Translate to Deutsch:] [Translate to Dansk:] Regular Element #1', 'Regular Element #2', 'Regular Element #3', '[DE] Without default language'],
            ],
            10 => [
                'typoScript' => 'config.sys_language_overlay = hideNonTranslated
                                config.sys_language_mode =',
                'visibleRecordHeaders' => ['Regular Element #1', 'Regular Element #2', 'Regular Element #3'],
            ],
            [
                'typoScript' => 'config.sys_language_overlay = hideNonTranslated
                                config.sys_language_mode = content_fallback',
                'visibleRecordHeaders' => ['Regular Element #1', 'Regular Element #2', 'Regular Element #3'],
            ],
            [
                'typoScript' => 'config.sys_language_overlay = hideNonTranslated
                                config.sys_language_mode = content_fallback;1,0',
                'visibleRecordHeaders' => ['[Translate to Dansk:] Regular Element #1', '[Translate to Dansk:] Regular Element #3', 'Regular Element #2', '[DK] Without default language', '[DK] UnHidden Element #4'],
            ],
            [
                'typoScript' => 'config.sys_language_overlay = hideNonTranslated
                                config.sys_language_mode = strict',
                'visibleRecordHeaders' => [],
                'status' => 404,
            ],
            [
                'typoScript' => 'config.sys_language_overlay = hideNonTranslated
                                config.sys_language_mode = ignore',
                'visibleRecordHeaders' => ['[Translate to Deutsch:] [Translate to Dansk:] Regular Element #1', 'Regular Element #2', 'Regular Element #3', '[DE] Without default language'],
            ],
        ];
    }

    /**
     * Page uid 89 is NOT translated to german
     *
     * @test
     * @dataProvider contentOnNonTranslatedPageDataProvider
     *
     * @param string $typoScript
     * @param array $visibleHeaders
     * @param string $status 'success' or 404
     */
    public function contentOnNonTranslatedPageGerman(string $typoScript, array $visibleHeaders, string $status='success')
    {
        $this->addTypoScriptToTemplateRecord(1, $typoScript);

        $frontendResponse = $this->getFrontendResponse(self::VALUE_PageId, 2);
        if ($status === Response::STATUS_Success) {
            $responseSections = $frontendResponse->getResponseSections('Extbase:list()');
            $this->assertThat(
                $responseSections,
                $this->getRequestSectionHasRecordConstraint()
                ->setTable(self::TABLE_Content)
                ->setField('header')
                ->setValues(...$visibleHeaders)
            );
            $this->assertThat(
                $responseSections,
                $this->getRequestSectionDoesNotHaveRecordConstraint()
                ->setTable(self::TABLE_Content)
                ->setField('header')
                ->setValues(...$this->getNonVisibleHeaders($visibleHeaders))
            );
        }
        //some configuration combinations results in 404, in that case status will be set to 404
        $this->assertEquals($status, $frontendResponse->getStatus());
    }

    public function contentOnPartiallyTranslatedPageDataProvider(): array
    {

        //Expected behaviour:
        //Setting sys_language_mode to different values doesn't influence the result as the requested page is translated to Polish,
        //Page title is always [PL]Page, and both sys_language_content and sys_language_uid are always 3
        return [
            [
                'typoScript' => 'config.sys_language_overlay = 0
                                config.sys_language_mode =',
                'visibleRecordHeaders' => ['Regular Element #3', '[Translate to Polski:] Regular Element #1', '[PL] Without default language'],
            ],
            [
                'typoScript' => 'config.sys_language_overlay = 0
                                config.sys_language_mode = content_fallback',
                'visibleRecordHeaders' => ['Regular Element #3', '[Translate to Polski:] Regular Element #1', '[PL] Without default language'],
            ],
            [
                'typoScript' => 'config.sys_language_overlay = 0
                                config.sys_language_mode = content_fallback;1,0',
                'visibleRecordHeaders' => ['Regular Element #3', '[Translate to Polski:] Regular Element #1', '[PL] Without default language'],
            ],
            [
                'typoScript' => 'config.sys_language_overlay = 0
                                config.sys_language_mode = strict',
                'visibleRecordHeaders' => ['[Translate to Polski:] Regular Element #1', '[PL] Without default language'],
            ],
            [
                'typoScript' => 'config.sys_language_overlay = 0
                                config.sys_language_mode = ignore',
                'visibleRecordHeaders' => ['Regular Element #3', '[Translate to Polski:] Regular Element #1', '[PL] Without default language'],
            ],
            5 => [
                'typoScript' => 'config.sys_language_overlay = 1
                                config.sys_language_mode =',
                'visibleRecordHeaders' => ['[PL] Without default language', '[Translate to Polski:] Regular Element #1', 'Regular Element #3'],
            ],
            // Expected behaviour:
            // Not translated element #2 is shown because sys_language_overlay = 1 (with sys_language_overlay = hideNonTranslated, it would be hidden)
            [
                'typoScript' => 'config.sys_language_overlay = 1
                                config.sys_language_mode = content_fallback',
                'visibleRecordHeaders' => ['[PL] Without default language', '[Translate to Polski:] Regular Element #1', 'Regular Element #3'],
            ],
//             Expected behaviour:
//             Element #3 is not translated in PL and it is translated in DK. It's not shown as content_fallback is not related to single CE level
//             but on page level - and this page is translated to Polish, so no fallback is happening
            [
                'typoScript' => 'config.sys_language_overlay = 1
                                config.sys_language_mode = content_fallback;1,0',
                'visibleRecordHeaders' => ['[PL] Without default language', '[Translate to Polski:] Regular Element #1', 'Regular Element #3'],
            ],
            [
                'typoScript' => 'config.sys_language_overlay = 1
                                config.sys_language_mode = strict',
                'visibleRecordHeaders' => ['[PL] Without default language', '[Translate to Polski:] Regular Element #1'],
            ],
            [
                'typoScript' => 'config.sys_language_overlay = 1
                                config.sys_language_mode = ignore',
                'visibleRecordHeaders' => ['[PL] Without default language', '[Translate to Polski:] Regular Element #1', 'Regular Element #3'],
            ],
            // Expected behaviour:
            // Non translated default language elements are not shown, because of hideNonTranslated
            10 => [
                'typoScript' => 'config.sys_language_overlay = hideNonTranslated
                                config.sys_language_mode =',
                'visibleRecordHeaders' => ['[PL] Without default language', 'Regular Element #3', '[Translate to Polski:] Regular Element #1'],
            ],
            [
                'typoScript' => 'config.sys_language_overlay = hideNonTranslated
                                config.sys_language_mode = content_fallback',
                'visibleRecordHeaders' => ['[PL] Without default language', 'Regular Element #3', '[Translate to Polski:] Regular Element #1'],
            ],
            [
                'typoScript' => 'config.sys_language_overlay = hideNonTranslated
                                config.sys_language_mode = content_fallback;1,0',
                'visibleRecordHeaders' => ['[PL] Without default language', 'Regular Element #3', '[Translate to Polski:] Regular Element #1'],
            ],
            [
                'typoScript' => 'config.sys_language_overlay = hideNonTranslated
                                config.sys_language_mode = strict',
                'visibleRecordHeaders' => ['[PL] Without default language', '[Translate to Polski:] Regular Element #1'],
            ],
            [
                'typoScript' => 'config.sys_language_overlay = hideNonTranslated
                                config.sys_language_mode = ignore',
                'visibleRecordHeaders' => ['[PL] Without default language', 'Regular Element #3', '[Translate to Polski:] Regular Element #1'],
            ]
        ];
    }

    /**
     * Page uid 89 is translated to to Polish, but not all CE are translated
     *
     * @test
     * @dataProvider contentOnPartiallyTranslatedPageDataProvider
     *
     * @param string $typoScript
     * @param array $visibleHeaders
     */
    public function contentOnPartiallyTranslatedPage(string $typoScript, array $visibleHeaders)
    {
        $this->addTypoScriptToTemplateRecord(1, $typoScript);

        $frontendResponse = $this->getFrontendResponse(self::VALUE_PageId, 3);
        $this->assertEquals('success', $frontendResponse->getStatus());
        $responseSections = $frontendResponse->getResponseSections('Extbase:list()');

        $this->assertThat(
            $responseSections,
            $this->getRequestSectionHasRecordConstraint()
            ->setTable(self::TABLE_Content)
            ->setField('header')
            ->setValues(...$visibleHeaders)
        );
        $this->assertThat(
            $responseSections,
            $this->getRequestSectionDoesNotHaveRecordConstraint()
            ->setTable(self::TABLE_Content)
            ->setField('header')
            ->setValues(...$this->getNonVisibleHeaders($visibleHeaders))
        );
    }

    /**
     * Helper function to ease asserting that rest of the data set is not visible
     *
     * @param array $visibleHeaders
     * @return array
     */
    protected function getNonVisibleHeaders(array $visibleHeaders): array
    {
        $allElements = [
            'Regular Element #1',
            'Regular Element #2',
            'Regular Element #3',
            'Hidden Element #4',
            '[Translate to Dansk:] Regular Element #1',
            '[Translate to Dansk:] Regular Element #3',
            '[DK] Without default language',
            '[DK] UnHidden Element #4',
            '[DE] Without default language',
            '[Translate to Deutsch:] [Translate to Dansk:] Regular Element #1',
            '[Translate to Polski:] Regular Element #1',
            '[PL] Without default language',
            '[PL] Hidden Regular Element #2'
        ];
        return array_diff($allElements, $visibleHeaders);
    }

    /**
     * Adds TypoScript setup snippet to the existing template record
     *
     * @param int $pageId
     * @param string $typoScript
     */
    protected function addTypoScriptToTemplateRecord(int $pageId, string $typoScript)
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('sys_template');

        $template = $connection->select(['*'], 'sys_template', ['pid' => $pageId, 'root' => 1])->fetch();
        if (empty($template)) {
            $this->fail('Cannot find root template on page with id: "' . $pageId . '"');
        }
        $updateFields['config'] = $template['config'] . LF . $typoScript;
        $connection->update(
            'sys_template',
            $updateFields,
            ['uid' => $template['uid']]
        );
    }
}