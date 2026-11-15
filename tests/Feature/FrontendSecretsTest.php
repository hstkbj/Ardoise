<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/** Rien de secret ne doit pouvoir arriver dans le code envoyé au navigateur. */
class FrontendSecretsTest extends TestCase
{
    private const SENSITIVE_NAME = '/SECRET|PRIVATE|PASSWORD|TOKEN|API_?KEY|WEBHOOK|DB_|MAIL_|AWS_/i';

    public function test_only_harmless_values_are_exposed_through_vite_variables(): void
    {
        $exposed = [];

        foreach (file(base_path('.env.example'), FILE_IGNORE_NEW_LINES) as $line) {
            if (preg_match('/^(VITE_[A-Z0-9_]+)=/', $line, $m)) {
                $exposed[] = $m[1];
            }
        }

        foreach ($this->frontendFiles() as $file) {
            preg_match_all('/import\.meta\.env\.(VITE_[A-Z0-9_]+)/', file_get_contents($file), $m);
            array_push($exposed, ...$m[1]);
        }

        $this->assertNotEmpty($exposed);

        foreach (array_unique($exposed) as $name) {
            $this->assertDoesNotMatchRegularExpression(self::SENSITIVE_NAME, $name, "{$name} serait visible par tous les visiteurs.");
        }
    }

    public function test_frontend_sources_hold_no_key_material(): void
    {
        foreach ($this->frontendFiles() as $file) {
            $this->assertDoesNotMatchRegularExpression(
                '/sk_(live|test)_[A-Za-z0-9]{8,}|-----BEGIN [A-Z ]*PRIVATE KEY-----|AKIA[0-9A-Z]{16}/',
                file_get_contents($file),
                "{$file} contient ce qui ressemble à une clé secrète.",
            );
        }
    }

    /** @return list<string> */
    private function frontendFiles(): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('js'), RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if (in_array($file->getExtension(), ['js', 'vue'], true)) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
