<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;

require_once __DIR__ . '/../../src/modules/forms/validation.php';

final class FileValidationTest extends DatabaseTestCase
{
    #[Test]
    public function validate_file_reference_rejects_non_numeric_answers(): void
    {
        $this->assertSame(
            [7 => 'Attachment has an invalid file reference.'],
            validate_file_reference(7, 'abc', 'Attachment')
        );
    }

    #[Test]
    public function validate_file_reference_rejects_missing_files(): void
    {
        $this->setCurrentUser($this->makeSessionUser(['id' => 12]));
        $this->pdo->setQueryHandler(static fn(string $sql): ?array => str_contains($sql, 'FROM files WHERE id = ?')
            ? ['rows' => []]
            : null);

        $this->assertSame(
            [7 => 'Attachment references a file that does not exist.'],
            validate_file_reference(7, 99, 'Attachment')
        );
    }

    #[Test]
    public function validate_file_reference_rejects_files_owned_by_another_user(): void
    {
        $this->setCurrentUser($this->makeSessionUser(['id' => 12]));
        $this->pdo->setQueryHandler(static fn(string $sql): ?array => str_contains($sql, 'FROM files WHERE id = ?')
            ? ['rows' => [[
                'id' => 99,
                'user_id' => 88,
                'question_id' => 7,
                'submission_id' => null,
            ]]]
            : null);

        $this->assertSame(
            [7 => 'Attachment references a file you do not own.'],
            validate_file_reference(7, 99, 'Attachment')
        );
    }

    #[Test]
    public function validate_file_reference_rejects_wrong_question_and_reused_submission_links(): void
    {
        $this->setCurrentUser($this->makeSessionUser(['id' => 12]));

        $this->pdo->setQueryHandler(static fn(string $sql): ?array => str_contains($sql, 'FROM files WHERE id = ?')
            ? ['rows' => [[
                'id' => 99,
                'user_id' => 12,
                'question_id' => 8,
                'submission_id' => null,
            ]]]
            : null);

        $this->assertSame(
            [7 => 'Attachment references a file for a different question.'],
            validate_file_reference(7, 99, 'Attachment')
        );

        $this->pdo->setQueryHandler(static fn(string $sql): ?array => str_contains($sql, 'FROM files WHERE id = ?')
            ? ['rows' => [[
                'id' => 99,
                'user_id' => 12,
                'question_id' => 7,
                'submission_id' => 123,
            ]]]
            : null);

        $this->assertSame(
            [7 => 'Attachment references a file that is already in use.'],
            validate_file_reference(7, 99, 'Attachment')
        );
    }

    #[Test]
    public function validate_file_reference_allows_file_reuse_for_the_same_submission_edit(): void
    {
        $this->setCurrentUser($this->makeSessionUser(['id' => 12]));
        $this->pdo->setQueryHandler(static fn(string $sql): ?array => str_contains($sql, 'FROM files WHERE id = ?')
            ? ['rows' => [[
                'id' => 99,
                'user_id' => 12,
                'question_id' => 7,
                'submission_id' => 123,
            ]]]
            : null);

        $this->assertSame([], validate_file_reference(7, 99, 'Attachment', 123));
    }

    #[Test]
    public function validate_file_upload_rejects_dangerous_extensions(): void
    {
        $file = $this->makeTempUpload('shell.php', 'plain text');

        $this->assertSame(
            ["File type 'php' is not permitted for security reasons."],
            validate_file_upload($file, [])
        );
    }

    #[Test]
    public function validate_file_upload_rejects_extensions_outside_the_safe_whitelist(): void
    {
        $file = $this->makeTempUpload('vector.svg', '<svg></svg>');

        $this->assertSame(
            ["File type 'svg' is not permitted. Allowed types: " . implode(', ', SAFE_FILE_EXTENSIONS)],
            validate_file_upload($file, [])
        );
    }

    #[Test]
    public function validate_file_upload_enforces_per_question_allowed_extensions(): void
    {
        $file = $this->makeTempUpload('photo.png', 'not really a png');

        $errors = validate_file_upload($file, ['allowed_extensions' => ['jpg']]);

        $this->assertContains('File type must be one of: jpg.', $errors);
    }

    #[Test]
    public function validate_file_upload_rejects_mime_mismatches_for_known_extensions(): void
    {
        $file = $this->makeTempUpload('photo.jpg', 'plain text pretending to be a jpeg');

        $errors = validate_file_upload($file, []);
        $this->assertContains("File content does not match its extension. Expected content type for 'jpg' files.", $errors);
    }

    #[Test]
    public function validate_file_upload_rejects_php_script_magic_bytes_even_with_safe_extension(): void
    {
        $file = $this->makeTempUpload('notes.txt', "<?php echo 'owned';");

        $errors = validate_file_upload($file, []);
        $this->assertTrue(
            in_array('File rejected: detected PHP script', $errors, true)
            || in_array("File content does not match its extension. Expected content type for 'txt' files.", $errors, true)
        );
    }

    /**
     * @return array{name:string,tmp_name:string,size:int}
     */
    private function makeTempUpload(string $filename, string $contents): array
    {
        $path = tempnam(sys_get_temp_dir(), 'formna-upload-');
        file_put_contents($path, $contents);

        return [
            'name' => $filename,
            'tmp_name' => $path,
            'size' => filesize($path),
        ];
    }
}