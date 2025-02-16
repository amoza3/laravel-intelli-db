<?php

namespace Amoza3\LaravelIntelliDb\Console;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;
use Amoza3\LaravelIntelliDb\OpenAi;
use Symfony\Component\Console\Input\InputOption;

class AiRepositoryCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $name = 'ai:repository';

    /**
     * The console command description.
     */
    protected $description = 'Create a new repository class using AI';

    public function __construct(private readonly OpenAi $openAi)
    {
        parent::__construct();
    }

    /**
     * Configure the command options.
     */
    protected function configure(): void
    {
        $this->addArgument('name', InputOption::VALUE_REQUIRED, 'The name of the repository (e.g., UserRepository)')
            ->addOption('model', 'm', InputOption::VALUE_REQUIRED, 'Optional: The Eloquent model this repository will handle')
            ->addOption('description', 'd', InputOption::VALUE_OPTIONAL, 'A description or specific instructions for the repository')
            ->addOption('path', 'p', InputOption::VALUE_OPTIONAL, 'The location where the repository file should be created');
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = $this->getNameInput();
        $model = $this->option('model');
        $description = $this->getDescriptionInput();
        $path = $this->option('path');

        $prompt = $this->createAiPrompt($name, $model, $description);

        $this->info('Generating AI-based repository, please wait...');

        try {
            $repoContent = $this->fetchAiGeneratedContent($prompt);

            if ($path && ! is_string($path)) {
                $this->error('Invalid path provided.');
                return 1;
            }

            $this->createRepositoryFile($name, $repoContent, $path);
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
     * Get the name input from the user.
     */
    private function getNameInput(): string
    {
        $name = $this->argument('name');
        if (! $name) {
            $name = $this->ask($this->promptForMissingArgumentsUsing()['name']);
        }

        // Convert to something like "UserRepository"
        return Str::studly(trim($name));
    }

    /**
     * Get the description input from the user.
     */
    private function getDescriptionInput(): ?string
    {
        $description = $this->option('description');
        if (! $description) {
            $description = $this->ask('Please describe the repository you want to generate (optional)', '');
        }

        return $description;
    }

    /**
     * Create an AI prompt for repository generation.
     */
    private function createAiPrompt(string $repositoryName, ?string $model, ?string $description): string
    {
        $prompt = "Generate a Laravel repository class named {$repositoryName}.\n";

        if ($model) {
            $prompt .= "It should be tailored to handle Eloquent operations for the {$model} model.\n";
        }

        if ($description) {
            $prompt .= "Additional instructions: {$description}\n";
        }

        $prompt .= "\nPlease include:\n";
        $prompt .= "- PHP opening tag and namespace (e.g., App\\Repositories or similar).\n";
        $prompt .= "- Class definition with correct name ({$repositoryName}).\n";
        $prompt .= "- Any necessary import statements for models, etc.\n";
        $prompt .= "- Type hints for methods and their arguments.\n";
        $prompt .= "- A summary of common repository methods (e.g. all, find, create, update, delete).\n";
        $prompt .= "\nReturn ONLY the final PHP code, no extra explanation or text.\n";

        return $prompt;
    }

    /**
     * Fetch the AI-generated content.
     *
     * @throws RequestException
     */
    private function fetchAiGeneratedContent(string $prompt): string
    {
        return $this->openAi->execute($prompt, 2000);
    }

    /**
     * Create the repository file.
     */
    private function createRepositoryFile(string $name, string $content, ?string $path): void
    {
        // We'll default the path to 'app/Repositories' if none provided.
        $path = $path ?? app_path('Repositories');

        if (! file_exists($path)) {
            mkdir($path, 0755, true);
        }

        // We'll save the file as "UserRepository.php" for instance
        $filename = $name . '.php';
        $filepath = $path . '/' . $filename;

        file_put_contents($filepath, $content);

        $this->info(sprintf('Repository [%s] created successfully at %s.', $name, $filepath));
    }

    /**
     * Prompt for missing arguments.
     *
     * @return array<string, string>
     */
    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'name' => 'What should the repository be named?',
        ];
    }
}
