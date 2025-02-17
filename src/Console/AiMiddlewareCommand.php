<?php

namespace Amoza3\LaravelIntelliDb\Console;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Amoza3\LaravelIntelliDb\OpenAi;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class AiMiddlewareCommand extends Command
{
    /**
     * نام دستور (signature) در artisan
     */
    protected $name = 'ai:middleware';

    /**
     * توضیحات دستور
     */
    protected $description = 'Create a new middleware class using AI';

    public function __construct(private readonly OpenAi $openAi)
    {
        parent::__construct();
    }

    /**
     * تنظیمات ورودی‌های دستور
     */
    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'The name of the middleware (e.g., CheckUserStatus)')
            ->addOption('description', 'd', InputOption::VALUE_OPTIONAL, 'A description of what the middleware should do')
            ->addOption('path', 'p', InputOption::VALUE_OPTIONAL, 'The location where the middleware file should be created');
    }

    /**
     * اجرای دستور
     */
    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));  // مثلاً => "CheckUserStatus"
        $description = $this->option('description') ?: "A middleware that handles {$name} logic";
        $path = $this->option('path') ?: app_path('Http/Middleware');

        $this->info("Generating Middleware for [{$name}] ...");

        try {
            // 1) تولید پرامپت برای ساخت Middleware
            $middlewarePrompt = $this->createAiPromptForMiddleware($name, $description);
            $middlewareContent = $this->fetchAiGeneratedContent($middlewarePrompt);
            $this->createMiddlewareFile($name, $middlewareContent, $path);

            $this->info('Middleware created successfully!');
        } catch (Exception $e) {
            $this->error('Error occurred: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * پرامپت مربوط به تولید Middleware
     */
    private function createAiPromptForMiddleware(string $name, string $description): string
    {
        return <<<PROMPT
You are a Laravel expert. Generate a PHP middleware class named {$name} that performs the following task:
Description: {$description}

Requirements:
- The middleware should be in the App\\Http\\Middleware namespace.
- It should implement the handle method.
- The handle method should take \$request and a closure, and return a response.
- If any conditions are met, the middleware should take an action (e.g., check for user authentication, handle permissions, etc.).
- Return only the complete PHP code (with <?php tag, namespace, and class definition). No extra explanations.
PROMPT;
    }

    /**
     * تماس با OpenAI برای دریافت کد تولید شده
     */
    private function fetchAiGeneratedContent(string $prompt): string
    {
        return $this->openAi->execute($prompt, 3000);
    }

    /**
     * ایجاد فایل Middleware
     */
    private function createMiddlewareFile(string $name, string $content, string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $filePath = $path . "/{$name}.php";
        file_put_contents($filePath, $content);

        $this->info("Middleware created: {$filePath}");
    }
}
