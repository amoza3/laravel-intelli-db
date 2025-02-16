<?php

namespace Amoza3\LaravelIntelliDb\Console;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;
use Amoza3\LaravelIntelliDb\OpenAi;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

/**
 * php artisan ai:repo-service User
 *   --description="ایجاد ریپازیتوری و سرویس برای کاربران"
 *   --model=User
 */

class AiRepositoryServiceCommand extends Command
{
    /**
     * نام دستور (signature) در artisan
     */
    protected $name = 'ai:repo-service';

    /**
     * توضیحات دستور
     */
    protected $description = 'Create a Repository Interface, Eloquent Repository, Service Class, and optionally a Model using AI';

    public function __construct(private readonly OpenAi $openAi)
    {
        parent::__construct();
    }

    /**
     * تنظیمات ورودی‌های دستور
     */
    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'The base name for the classes (e.g., User, Product)')
            ->addOption('description', 'd', InputOption::VALUE_OPTIONAL, 'A brief description of the repository/service')
            ->addOption('model', 'm', InputOption::VALUE_OPTIONAL, 'Model name (defaults to the "name" argument if not provided)')
            ->addOption('create-model', null, InputOption::VALUE_NONE, 'If set, a model file will be generated as well');
    }

    /**
     * اجرای دستور
     */
    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));  // مثلاً => "User"
        $description = $this->option('description') ?: "Create a repository and service for $name";
        $modelName = $this->option('model') ?: $name;
        $shouldCreateModel = $this->option('create-model');

        $this->info("Generating Repository + Service for [{$name}] ...");

        try {
            // 1) تولید فایل Interface
            $interfacePrompt = $this->createAiPromptForInterface($name, $modelName, $description);
            $interfaceContent = $this->fetchAiGeneratedContent($interfacePrompt);
            $this->createInterfaceFile($name, $interfaceContent);

            // 2) تولید فایل Repository (Eloquent)
            $repositoryPrompt = $this->createAiPromptForRepository($name, $modelName, $description);
            $repositoryContent = $this->fetchAiGeneratedContent($repositoryPrompt);
            $this->createRepositoryFile($name, $repositoryContent);

            // 3) تولید فایل Service
            $servicePrompt = $this->createAiPromptForService($name, $modelName, $description);
            $serviceContent = $this->fetchAiGeneratedContent($servicePrompt);
            $this->createServiceFile($name, $serviceContent);

            // 4) تولید فایل Model (اختیاری)
            if ($shouldCreateModel) {
                $modelPrompt = $this->createAiPromptForModel($modelName, $description);
                $modelContent = $this->fetchAiGeneratedContent($modelPrompt);
                $this->createModelFile($modelName, $modelContent);
            }

            $this->info('All files were generated successfully!');
        } catch (RequestException $e) {
            $this->error('Error fetching AI-generated content: ' . $e->getMessage());
            return 1;
        } catch (Exception $e) {
            $this->error('Error occurred: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * پرامپت مربوط به تولید Interface
     */
    private function createAiPromptForInterface(string $name, string $model, string $description): string
    {
        return <<<PROMPT
You are a Laravel expert. Please generate a PHP interface for {$name}Repository that manages the {$model} model.
Description: {$description}

Requirements:
- The interface name: {$name}RepositoryInterface
- Namespace: App\\Repositories\\Contracts
- It should have at least these methods: all(), find(\$id), create(\$data), update(\$id, \$data), delete(\$id).
- Return only the complete PHP code (with <?php tag, namespace, interface definition). No extra explanations.
PROMPT;
    }

    /**
     * پرامپت مربوط به تولید Eloquent Repository
     */
    private function createAiPromptForRepository(string $name, string $model, string $description): string
    {
        return <<<PROMPT
You are a Laravel expert. Generate a PHP class named Eloquent{$name}Repository that implements {$name}RepositoryInterface.
Description: {$description}

Requirements:
- Namespace: App\\Repositories
- Implement the methods from the interface.
- Use the Eloquent model: App\\Models\\{$model}.
- Return only the complete PHP code. No extra explanations.
- Include use statements and type hints as needed.
PROMPT;
    }

    /**
     * پرامپت تولید Service
     */
    private function createAiPromptForService(string $name, string $model, string $description): string
    {
        return <<<PROMPT
You are a Laravel expert. Generate a PHP service class named {$name}Service for handling business logic related to the {$model} model.
Description: {$description}

Requirements:
- Namespace: App\\Services
- It should use App\\Repositories\\Contracts\\{$name}RepositoryInterface in its constructor.
- Include methods like getAll(), getById(\$id), create(\$data), update(\$id, \$data), delete(\$id).
- Return only the complete PHP code. No extra explanations.
- Include use statements and type hints as needed.
PROMPT;
    }

    /**
     * پرامپت تولید Model (اختیاری)
     */
    private function createAiPromptForModel(string $model, string $description): string
    {
        return <<<PROMPT
You are a Laravel expert. Generate a simple Eloquent model class named {$model} in Laravel.
Description: {$description}

Requirements:
- Namespace: App\\Models
- Extend Illuminate\\Database\\Eloquent\\Model
- Protected \$fillable array with sample fields (e.g. ['name', 'email']).
- Return only the complete PHP code. No extra explanations.
PROMPT;
    }

    /**
     * تماس با OpenAI برای دریافت کد تولید شده
     */
    private function fetchAiGeneratedContent(string $prompt): string
    {
        // فرض بر این است که متد execute در کلاس OpenAi شما
        // یک درخواست به ChatGPT/OpenAI می‌فرستد و پاسخ را برمی‌گرداند
        return $this->openAi->execute($prompt, 3000);
    }

    /**
     * ایجاد فایل اینترفیس
     */
    private function createInterfaceFile(string $name, string $content): void
    {
        $directory = app_path('Repositories/Contracts');
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filePath = $directory . "/{$name}RepositoryInterface.php";
        file_put_contents($filePath, $content);

        $this->info("Interface created: {$filePath}");
    }

    /**
     * ایجاد فایل ریپازیتوری
     */
    private function createRepositoryFile(string $name, string $content): void
    {
        $directory = app_path('Repositories');
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filePath = $directory . "/Eloquent{$name}Repository.php";
        file_put_contents($filePath, $content);

        $this->info("Eloquent Repository created: {$filePath}");
    }

    /**
     * ایجاد فایل سرویس
     */
    private function createServiceFile(string $name, string $content): void
    {
        $directory = app_path('Services');
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filePath = $directory . "/{$name}Service.php";
        file_put_contents($filePath, $content);

        $this->info("Service created: {$filePath}");
    }

    /**
     * ایجاد فایل مدل (اختیاری)
     */
    private function createModelFile(string $model, string $content): void
    {
        $directory = app_path('Models');
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filePath = $directory . "/{$model}.php";
        file_put_contents($filePath, $content);

        $this->info("Model created: {$filePath}");
    }
}
