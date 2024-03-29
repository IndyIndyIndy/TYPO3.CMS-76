package core;

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

import java.util.ArrayList;

import com.atlassian.bamboo.specs.api.builders.BambooKey;
import com.atlassian.bamboo.specs.api.builders.permission.PermissionType;
import com.atlassian.bamboo.specs.api.builders.permission.Permissions;
import com.atlassian.bamboo.specs.api.builders.permission.PlanPermissions;
import com.atlassian.bamboo.specs.api.builders.plan.Job;
import com.atlassian.bamboo.specs.api.builders.plan.PlanIdentifier;
import com.atlassian.bamboo.specs.api.builders.plan.configuration.AllOtherPluginsConfiguration;
import com.atlassian.bamboo.specs.api.builders.plan.configuration.PluginConfiguration;
import com.atlassian.bamboo.specs.api.builders.requirement.Requirement;
import com.atlassian.bamboo.specs.api.builders.task.Task;
import com.atlassian.bamboo.specs.builders.task.CheckoutItem;
import com.atlassian.bamboo.specs.builders.task.CommandTask;
import com.atlassian.bamboo.specs.builders.task.ScriptTask;
import com.atlassian.bamboo.specs.builders.task.TestParserTask;
import com.atlassian.bamboo.specs.builders.task.VcsCheckoutTask;
import com.atlassian.bamboo.specs.model.task.ScriptTaskProperties;
import com.atlassian.bamboo.specs.model.task.TestParserTaskProperties;
import com.atlassian.bamboo.specs.util.MapBuilder;

/**
 * Abstract class with common methods of pre-merge and nightly plan
 */
abstract public class AbstractCoreSpec {

    protected static String bambooServerName = "https://bamboo.typo3.com:443";
    protected static String projectName = "TYPO3 Core";
    protected static String projectKey = "CORE";

    protected String testingFrameworkBuildPath = "typo3/sysext/core/Build/";

    /**
     * Default permissions on core plans
     *
     * @param projectName
     * @param planName
     * @return
     */
    protected PlanPermissions getDefaultPlanPermissions(String projectKey, String planKey) {
        return new PlanPermissions(new PlanIdentifier(projectKey, planKey))
            .permissions(new Permissions()
            .groupPermissions("TYPO3 GmbH", PermissionType.ADMIN, PermissionType.VIEW, PermissionType.EDIT, PermissionType.BUILD, PermissionType.CLONE)
            .groupPermissions("TYPO3 Core Team", PermissionType.VIEW, PermissionType.BUILD)
            .loggedInUserPermissions(PermissionType.VIEW)
            .anonymousUserPermissionView()
        );
    }

    /**
     * Default plan plugin configuration
     *
     * @return
     */
    protected PluginConfiguration getDefaultPlanPluginConfiguration() {
        return new AllOtherPluginsConfiguration()
            .configuration(new MapBuilder()
            .put("custom", new MapBuilder()
                .put("artifactHandlers.useCustomArtifactHandlers", "false")
                .put("buildExpiryConfig", new MapBuilder()
                    .put("duration", "30")
                    .put("period", "days")
                    .put("labelsToKeep", "")
                    .put("expiryTypeResult", "true")
                    .put("buildsToKeep", "")
                    .put("enabled", "true")
                    .build()
                )
                .build()
            )
            .build()
        );
    }

    /**
     * Default job plugin configuration
     *
     * @return
     */
    protected PluginConfiguration getDefaultJobPluginConfiguration() {
        return new AllOtherPluginsConfiguration()
            .configuration(new MapBuilder()
                .put("repositoryDefiningWorkingDirectory", -1)
                .put("custom", new MapBuilder()
                    .put("auto", new MapBuilder()
                        .put("regex", "")
                        .put("label", "")
                        .build()
                    )
                    .put("buildHangingConfig.enabled", "false")
                    .put("ncover.path", "")
                    .put("clover", new MapBuilder()
                        .put("path", "")
                        .put("license", "")
                        .put("useLocalLicenseKey", "true")
                        .build()
                    )
                    .build()
                )
                .build()
            );
    }

    /**
     * Job composer validate
     *
     * @param String requirementIdentifier
     */
    protected Job getJobComposerValidate(String requirementIdentifier) {
        return new Job("Validate composer.json", new BambooKey("VC"))
        .description("Validate composer.json before actual tests are executed")
        .pluginConfigurations(this.getDefaultJobPluginConfiguration())
        .tasks(
            this.getTaskGitCloneRepository(),
            this.getTaskGitCherryPick(),
            new ScriptTask()
                .description("composer validate")
                .interpreter(ScriptTaskProperties.Interpreter.BINSH_OR_CMDEXE)
                .inlineBody(
                    this.getScriptTaskBashInlineBody() +
                    this.getScriptTaskComposer(requirementIdentifier) +
                    "composer validate"
                )
        )
        .requirements(
            this.getRequirementDocker10()
        )
        .cleanWorkingDirectory(true);
    }

    /**
     * Jobs for mysql based functional tests
     *
     * @param int numberOfChunks
     * @param String requirementIdentifier
     */
    protected ArrayList<Job> getJobsFunctionalTestsMysql(int numberOfChunks, String requirementIdentifier) {
        ArrayList<Job> jobs = new ArrayList<Job>();

        for (int i=0; i<numberOfChunks; i++) {
            jobs.add(new Job("Func mysql " + requirementIdentifier + " 0" + i, new BambooKey("FMY" + requirementIdentifier + "0" + i))
                .description("Run functional tests on mysql DB " + requirementIdentifier)
                .pluginConfigurations(this.getDefaultJobPluginConfiguration())
                .tasks(
                    this.getTaskGitCloneRepository(),
                    this.getTaskGitCherryPick(),
                    this.getTaskComposerInstall(requirementIdentifier),
                    this.getTaskDockerDependenciesFunctionalMariadb10(),
                    this.getTaskSplitFunctionalJobs(numberOfChunks),
                    new ScriptTask()
                        .description("Run phpunit with functional chunk 0" + i)
                        .interpreter(ScriptTaskProperties.Interpreter.BINSH_OR_CMDEXE)
                        .inlineBody(
                            this.getScriptTaskBashInlineBody() +
                            "function phpunit() {\n" +
                            "    docker run \\\n" +
                            "        -u ${HOST_UID} \\\n" +
                            "        -v /bamboo-data/${BAMBOO_COMPOSE_PROJECT_NAME}/passwd:/etc/passwd \\\n" +
                            "        -v ${BAMBOO_COMPOSE_PROJECT_NAME}_bamboo-data:/srv/bamboo/xml-data/build-dir/ \\\n" +
                            "        -e typo3DatabaseName=func_test \\\n" +
                            "        -e typo3DatabaseUsername=root \\\n" +
                            "        -e typo3DatabasePassword=funcp \\\n" +
                            "        -e typo3DatabaseHost=mariadb10 \\\n" +
                            "        -e typo3TestingRedisHost=${BAMBOO_COMPOSE_PROJECT_NAME}sib_redis4_1 \\\n" +
                            "        -e typo3TestingMemcachedHost=${BAMBOO_COMPOSE_PROJECT_NAME}sib_memcached1-5_1 \\\n" +
                            "        --name ${BAMBOO_COMPOSE_PROJECT_NAME}sib_adhoc \\\n" +
                            "        --network ${BAMBOO_COMPOSE_PROJECT_NAME}_test \\\n" +
                            "        --rm \\\n" +
                            "        typo3gmbh/" + requirementIdentifier.toLowerCase() + ":latest \\\n" +
                            "        bin/bash -c \"cd ${PWD}; ./bin/phpunit $*\"\n" +
                            "}\n" +
                            "\n" +
                            "phpunit --log-junit test-reports/phpunit.xml -c " + this.testingFrameworkBuildPath + "FunctionalTests-Job-" + i + ".xml"
                        )
                )
                .finalTasks(
                    this.getTaskStopDockerDependencies(),
                    new TestParserTask(TestParserTaskProperties.TestType.JUNIT)
                        .resultDirectories("test-reports/phpunit.xml")
                )
                .requirements(
                    this.getRequirementDocker10()
                )
                .cleanWorkingDirectory(true)
            );
        }

        return jobs;
    }

    /**
     * Job with various smaller script tests
     *
     * @param String requirementIdentifier
     */
    protected Job getJobIntegrationVarious(String requirementIdentifier) {
        // Exception code checker, xlf, permissions, rst file check
        return new Job("Integration various", new BambooKey("CDECC"))
            .description("Checks duplicate exceptions, git submodules, xlf files, permissions, rst")
            .pluginConfigurations(this.getDefaultJobPluginConfiguration())
            .tasks(
                this.getTaskGitCloneRepository(),
                this.getTaskGitCherryPick(),
                new ScriptTask()
                    .description("Run git submodule status and verify there are none")
                    .interpreter(ScriptTaskProperties.Interpreter.BINSH_OR_CMDEXE)
                    .inlineBody(
                        this.getScriptTaskBashInlineBody() +
                        "if [[ `git submodule status 2>&1 | wc -l` -ne 0 ]]; then\n" +
                        "    echo \\\"Found a submodule definition in repository\\\";\n" +
                        "    exit 99;\n" +
                        "fi\n"
                    ),
                new ScriptTask()
                    .description("Run xlf check")
                    .interpreter(ScriptTaskProperties.Interpreter.BINSH_OR_CMDEXE)
                    .inlineBody(
                        this.getScriptTaskBashInlineBody() +
                        "./typo3/sysext/core/Build/Scripts/xlfcheck.sh"
                    )
            )
            .requirements(
                this.getRequirementDocker10()
            )
            .cleanWorkingDirectory(true);
    }

    /**
     * Job for PHP lint
     *
     * @param String requirementIdentfier
     */
    protected Job getJobLintPhp(String requirementIdentifier) {
        return new Job("Lint " + requirementIdentifier, new BambooKey("L" + requirementIdentifier))
            .description("Run php -l on source files for linting " + requirementIdentifier)
            .pluginConfigurations(this.getDefaultJobPluginConfiguration())
            .tasks(
                this.getTaskGitCloneRepository(),
                this.getTaskGitCherryPick(),
                new ScriptTask()
                    .description("Run php lint")
                    .interpreter(ScriptTaskProperties.Interpreter.BINSH_OR_CMDEXE)
                    .inlineBody(
                        this.getScriptTaskBashInlineBody() +
                        "function runLint() {\n" +
                        "    docker run \\\n" +
                        "        -u ${HOST_UID} \\\n" +
                        "        -v /bamboo-data/${BAMBOO_COMPOSE_PROJECT_NAME}/passwd:/etc/passwd \\\n" +
                        "        -v ${BAMBOO_COMPOSE_PROJECT_NAME}_bamboo-data:/srv/bamboo/xml-data/build-dir/ \\\n" +
                        "        -e HOME=${HOME} \\\n" +
                        "        --name ${BAMBOO_COMPOSE_PROJECT_NAME}sib_adhoc \\\n" +
                        "        --rm \\\n" +
                        "        typo3gmbh/" + requirementIdentifier.toLowerCase() + ":latest \\\n" +
                        "        bin/bash -c \"cd ${PWD}; find . -name \\*.php -print0 | xargs -0 -n1 -P2 php -l >/dev/null\"\n" +
                        "}\n" +
                        "\n" +
                        "runLint"
                    )
            )
            .requirements(
                this.getRequirementDocker10()
            )
            .cleanWorkingDirectory(true);
    }

    /**
     * Job for unit testing PHP
     *
     * @param String requirementIdentfier
     */
    protected Job getJobUnitPhp(String requirementIdentifier) {
        return new Job("Unit " + requirementIdentifier, new BambooKey("UT" + requirementIdentifier))
            .description("Run unit tests " + requirementIdentifier)
            .pluginConfigurations(this.getDefaultJobPluginConfiguration())
            .tasks(
                this.getTaskGitCloneRepository(),
                this.getTaskGitCherryPick(),
                this.getTaskComposerInstall(requirementIdentifier),
                this.getTaskDockerDependenciesUnit(),
                new ScriptTask()
                    .description("Run phpunit")
                    .interpreter(ScriptTaskProperties.Interpreter.BINSH_OR_CMDEXE)
                    .inlineBody(
                        this.getScriptTaskBashInlineBody() +
                        "function phpunit() {\n" +
                        "    docker run \\\n" +
                        "        -u ${HOST_UID} \\\n" +
                        "        -v /bamboo-data/${BAMBOO_COMPOSE_PROJECT_NAME}/passwd:/etc/passwd \\\n" +
                        "        -v ${BAMBOO_COMPOSE_PROJECT_NAME}_bamboo-data:/srv/bamboo/xml-data/build-dir/ \\\n" +
                        "        -e typo3TestingRedisHost=redis4 \\\n" +
                        "        -e typo3TestingMemcachedHost=memcached1-5 \\\n" +
                        "        --name ${BAMBOO_COMPOSE_PROJECT_NAME}sib_adhoc \\\n" +
                        "        --network ${BAMBOO_COMPOSE_PROJECT_NAME}_test \\\n" +
                        "        --rm \\\n" +
                        "        typo3gmbh/" + requirementIdentifier.toLowerCase() + ":latest \\\n" +
                        "        bin/bash -c \"cd ${PWD}; php bin/phpunit $*\"\n" +
                        "}\n" +
                        "\n" +
                        "phpunit --log-junit test-reports/phpunit.xml -c " + this.testingFrameworkBuildPath + "UnitTests.xml"
                    )
            )
            .finalTasks(
                this.getTaskStopDockerDependencies(),
                new TestParserTask(TestParserTaskProperties.TestType.JUNIT)
                    .resultDirectories("test-reports/phpunit.xml")
            )
            .requirements(
                this.getRequirementDocker10()
            )
            .cleanWorkingDirectory(true);
    }

    /**
     * Task definition for basic core clone of linked default repository
     */
    protected Task getTaskGitCloneRepository() {
        return new VcsCheckoutTask()
            .description("Checkout git core")
            .checkoutItems(new CheckoutItem().defaultRepository());
    }

    /**
     * Task definition to cherry pick a patch set from gerrit on top of cloned core
     */
    protected Task getTaskGitCherryPick() {
        return new ScriptTask()
            .description("Gerrit cherry pick")
            .interpreter(ScriptTaskProperties.Interpreter.BINSH_OR_CMDEXE)
            .inlineBody(
                this.getScriptTaskBashInlineBody() +
                "CHANGEURL=${bamboo.changeUrl}\n" +
                "CHANGEURLID=${CHANGEURL#https://review.typo3.org/}\n" +
                "PATCHSET=${bamboo.patchset}\n" +
                "\n" +
                "if [[ $CHANGEURL ]]; then\n" +
                "    gerrit-cherry-pick https://review.typo3.org/Packages/TYPO3.CMS $CHANGEURLID/$PATCHSET || exit 1\n" +
                "fi\n"
            );
    }

    /**
     * Task definition to execute composer install
     *
     * @param String requirementIdentifier
     */
    protected Task getTaskComposerInstall(String requirementIdentifier) {
        return new ScriptTask()
            .description("composer install")
            .interpreter(ScriptTaskProperties.Interpreter.BINSH_OR_CMDEXE)
            .inlineBody(
                this.getScriptTaskBashInlineBody() +
                this.getScriptTaskComposer(requirementIdentifier) +
                "composer install -n"
            );
    }

    /**
     * Start docker sibling containers to execute functional tests on mariadb
     */
    protected Task getTaskDockerDependenciesFunctionalMariadb10() {
        return new ScriptTask()
            .description("Start docker siblings for functional tests on mariadb")
            .interpreter(ScriptTaskProperties.Interpreter.BINSH_OR_CMDEXE)
            .inlineBody(
                this.getScriptTaskBashInlineBody() +
                "cd Build/testing-docker/bamboo\n" +
                "echo COMPOSE_PROJECT_NAME=${BAMBOO_COMPOSE_PROJECT_NAME}sib > .env\n" +
                "docker-compose run start_dependencies_functional_mariadb10"
            );
    }

    /**
     * Start docker sibling containers to execute unit tests
     */
    protected Task getTaskDockerDependenciesUnit() {
        return new ScriptTask()
            .description("Start docker siblings for unit tests")
            .interpreter(ScriptTaskProperties.Interpreter.BINSH_OR_CMDEXE)
            .inlineBody(
                this.getScriptTaskBashInlineBody() +
                "cd Build/testing-docker/bamboo\n" +
                "echo COMPOSE_PROJECT_NAME=${BAMBOO_COMPOSE_PROJECT_NAME}sib > .env\n" +
                "docker-compose run start_dependencies_unit"
            );
    }

    /**
     * Stop started docker containers
     */
    protected Task getTaskStopDockerDependencies() {
        return new ScriptTask()
            .description("Stop docker siblings")
            .interpreter(ScriptTaskProperties.Interpreter.BINSH_OR_CMDEXE)
            .inlineBody(
                this.getScriptTaskBashInlineBody() +
                "cd Build/testing-docker/bamboo\n" +
                "docker-compose down -v"
            );
    }

    /**
     * Task to split functional jobs into chunks
     */
    protected Task getTaskSplitFunctionalJobs(int numberOfJobs) {
        return new ScriptTask()
            .description("Create list of test files to execute per job")
            .interpreter(ScriptTaskProperties.Interpreter.BINSH_OR_CMDEXE)
            .inlineBody(
                this.getScriptTaskBashInlineBody() +
                "./" + this.testingFrameworkBuildPath + "Scripts/splitFunctionalTests.sh " + numberOfJobs
            );
    }

    /**
     * Requirement for docker 1.0 set by bamboo-agents
     */
    protected Requirement getRequirementDocker10() {
        return new Requirement("system.hasDocker")
            .matchValue("1.0")
            .matchType(Requirement.MatchType.EQUALS);
    }

    /**
     * A bash header for script tasks forking a bash if needed
     */
    protected String getScriptTaskBashInlineBody() {
        return
            "#!/bin/bash\n" +
            "\n" +
            "if [ \"$(ps -p \"$$\" -o comm=)\" != \"bash\" ]; then\n" +
            "    bash \"$0\" \"$@\"\n" +
            "    exit \"$?\"\n" +
            "fi\n" +
            "\n" +
            "set -x\n" +
            "\n";
    }

    /**
     * A bash function aliasing 'composer' as docker command
     *
     * @param String requirementIdentifier
     */
    protected String getScriptTaskComposer(String requirementIdentifier) {
        return
            "function composer() {\n" +
            "    docker run \\\n" +
            "        -u ${HOST_UID} \\\n" +
            "        -v /bamboo-data/${BAMBOO_COMPOSE_PROJECT_NAME}/passwd:/etc/passwd \\\n" +
            "        -v ${BAMBOO_COMPOSE_PROJECT_NAME}_bamboo-data:/srv/bamboo/xml-data/build-dir/ \\\n" +
            "        -e HOME=${HOME} \\\n" +
            "        --name ${BAMBOO_COMPOSE_PROJECT_NAME}sib_adhoc \\\n" +
            "        --rm \\\n" +
            "        typo3gmbh/" + requirementIdentifier.toLowerCase() + ":latest \\\n" +
            "        bin/bash -c \"cd ${PWD}; composer $*\"\n" +
            "}\n" +
            "\n";
    }

    /**
     * A bash function providing a php bin without xdebug
     */
    protected String getScriptTaskBashPhpNoXdebug() {
        return
            "php_no_xdebug () {\n" +
            "    temporaryPath=\"$(mktemp -t php.XXXX).ini\"\n" +
            "    php -i | grep \"\\.ini\" | grep -o -e '\\(/[A-Za-z0-9._-]\\+\\)\\+\\.ini' | grep -v xdebug | xargs awk 'FNR==1{print \"\"}1' > \"${temporaryPath}\"\n" +
            "    php -n -c \"${temporaryPath}\" \"$@\"\n" +
            "    RETURN=$?\n" +
            "    rm -f \"${temporaryPath}\"\n" +
            "    exit $RETURN\n" +
            "}\n" +
            "\n";
    }
}
