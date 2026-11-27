<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected Database $db;

    public function __construct(protected readonly App $app, protected readonly Request $request)
    {
        // The setup wizard runs before a database exists.
        if ($app->hasDatabase()) {
            $this->db = $app->db();
        }
    }

    protected function view(string $template, array $data = [], ?string $layout = 'layouts/app'): Response
    {
        return Response::html($this->app->view()->render($template, $data, $layout));
    }

    protected function redirect(string $path, array $query = []): Response
    {
        return Response::redirect(url($path, $query));
    }

    protected function back(string $fallback = '/'): Response
    {
        return Response::redirect($this->safeBackUrl($fallback));
    }

    /** Only follow a Referer header that points back into this app. */
    protected function safeBackUrl(string $fallback): string
    {
        $referer = $this->request->referer();
        if ($referer) {
            $host = $this->request->server['HTTP_HOST'] ?? '';
            $parts = parse_url($referer);
            if (($parts['host'] ?? '') === $host && isset($parts['path'])) {
                return $parts['path'] . (isset($parts['query']) ? '?' . $parts['query'] : '');
            }
        }

        return url($fallback);
    }

    protected function success(string $message): void
    {
        $this->app->session()->flash('success', $message);
    }

    protected function error(string $message): void
    {
        $this->app->session()->flash('error', $message);
    }

    protected function authorize(string $ability): void
    {
        if (! $this->app->auth()->can($ability)) {
            throw new HttpException(403);
        }
    }

    protected function findOrFail(string $table, int|string $id): array
    {
        $row = $this->db->find($table, (int) $id);
        if (! $row) {
            throw new HttpException(404);
        }

        return $row;
    }

    /**
     * Validate request input. Rules: required, nullable, string, int, numeric, min:n, max:n,
     * email, date, in:a,b,c, bool. Returns trimmed values ('' becomes null when not required).
     */
    protected function validate(array $rules, array $labels = []): array
    {
        $clean = [];
        $errors = [];
        foreach ($rules as $field => $ruleString) {
            $rulesList = explode('|', $ruleString);
            $label = $labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
            $raw = $this->request->input($field);

            if (in_array('bool', $rulesList, true)) {
                $clean[$field] = $this->request->bool($field) ? 1 : 0;
                continue;
            }
            $value = is_scalar($raw) ? trim((string) $raw) : '';

            if ($value === '') {
                if (in_array('required', $rulesList, true)) {
                    $errors[$field] = $label . ' is required.';
                }
                $clean[$field] = null;
                continue;
            }

            foreach ($rulesList as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                $message = match ($name) {
                    'int' => preg_match('/^-?\d+$/', $value) ? null : $label . ' must be a whole number.',
                    'numeric' => is_numeric($value) ? null : $label . ' must be a number.',
                    'min' => (is_numeric($value) ? (float) $value >= (float) $arg : mb_strlen($value) >= (int) $arg)
                        ? null : $label . ' must be at least ' . $arg . '.',
                    'max' => (in_array('int', $rulesList, true) || in_array('numeric', $rulesList, true)
                        ? (float) $value <= (float) $arg : mb_strlen($value) <= (int) $arg)
                        ? null : $label . ' must be at most ' . $arg . (in_array('int', $rulesList, true) ? '.' : ' characters.'),
                    'email' => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : $label . ' must be a valid email address.',
                    'date' => $this->isDate($value) ? null : $label . ' must be a valid date.',
                    'in' => in_array($value, explode(',', (string) $arg), true) ? null : $label . ' is not a valid choice.',
                    default => null,
                };
                if ($message !== null) {
                    $errors[$field] = $message;
                    break;
                }
            }
            $clean[$field] = in_array('int', $rulesList, true) && ! isset($errors[$field]) ? (int) $value : $value;
        }

        if ($errors) {
            throw new ValidationException($errors);
        }

        return $clean;
    }

    private function isDate(string $value): bool
    {
        foreach (['Y-m-d', 'Y-m-d\TH:i', 'Y-m-d H:i', 'Y-m-d H:i:s'] as $format) {
            $d = \DateTime::createFromFormat('!' . $format, $value);
            if ($d && $d->format($format) === $value) {
                return true;
            }
        }

        return false;
    }

    /** Normalise a datetime-local value to Y-m-d H:i:s (or null). */
    protected function datetime(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $ts = strtotime($value);

        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }

    protected function userId(): ?int
    {
        return $this->app->auth()->id();
    }
}
