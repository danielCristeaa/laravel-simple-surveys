[← Back to README](../README.md)

# Headless / API-Only Setup (React, Vue, Next.js, Mobile)

If your Laravel backend serves a separate SPA or mobile client over Sanctum, Passport, or another API guard, you can consume the package's built-in JSON endpoints without rendering the Blade widget.

---

## 1. Configure Guard, Prefix & Middleware

Publish `config/simple-surveys.php` and configure your API guard and route settings:

```php
'guard' => 'sanctum', // Required for stateless Bearer tokens ('sanctum', 'api', 'passport')

'routes' => [
    'enabled' => true,
    'prefix' => 'api/simple-surveys',
    'middleware' => ['api', 'auth:sanctum'],
],
```

> **Important (Bearer Tokens):** `GET /{prefix}/eligible` intentionally strips `auth` / `auth:*` middleware so unauthenticated visitors receive `200 {"survey": null}` instead of `401`. Because of this, you **must** set `'guard' => 'sanctum'` (or your API guard) when authenticating via `Authorization: Bearer ...` headers—leaving `'guard' => null` falls back to the `web` session guard and returns `{"survey": null}`.

---

## 2. Configure CORS, Page Targeting & Admin

- **Cross-origin SPAs:** Add your route prefix (e.g. `'api/simple-surveys/*'`) to `paths` in Laravel's `config/cors.php`.
- **Page targeting:** Because Laravel does not know client-side React Router / Vue Router route names, pass the current browser path as `?url=/dashboard` to `GET /{prefix}/eligible` and use `->onPaths(...)` / `->exceptPaths(...)` in your survey definitions instead of `->onRoutes(...)`.
- **Admin Dashboard:** `/simple-surveys/admin` is a session-authenticated Blade UI. Keep a lightweight web login for admins, or disable it with `'admin' => ['enabled' => false]`.

---

## 3. JSON Endpoints Reference

All `POST` routes enforce your configured `routes.middleware` and expect `Accept: application/json`. Read-only `GET` routes include `Cache-Control: no-store, private`.

| Endpoint | Method | Query / Body Parameters | Response |
| :--- | :--- | :--- | :--- |
| `/{prefix}/eligible` | `GET` | `?survey=<key>` (optional: check a specific survey), `?url=<path>` (optional: evaluate `onPaths`/`exceptPaths`) | `200 {"survey": {...}}` or `200 {"survey": null}` |
| `/{prefix}/{key}/impression` | `POST` | `{ url? }` | `200 {"status": "seen" \| "disabled", "survey_key": "..."}` — records unique impression and auto-syncs survey on first impression |
| `/{prefix}/{key}/open` | `POST` | `{ url? }` | `200 {"status": "opened", "survey_key": "..."}` — increments open count, auto-syncs survey on first open, and powers server-side `duration_ms` fallback |
| `/{prefix}/{key}/responses` | `POST` | `{ answers: {...}, url?, duration_ms? }` | `201 {"message": "...", "survey_key": "..."}` (or `422` validation errors, `403` inactive/ineligible, `409` already submitted/dismissed) |
| `/{prefix}/{key}/dismiss` | `POST` | `{ source?: "banner" \| "card", url? }` | `200 {"status": "dismissed", "survey_key": "..."}` (or `403` non-dismissible, `409` already completed) |

### `GET /{prefix}/eligible` Payload Shape

```json
{
  "survey": {
    "key": "onboarding-feedback",
    "title": "Quick Onboarding Survey",
    "banner_text": "Got 30 seconds? Help us improve onboarding!",
    "description": "Your feedback directly shapes what we build next.",
    "submit_label": "Submit",
    "thank_you_message": "Thank you for your feedback!",
    "dismissible": true,
    "reprompt_after_dismiss": false,
    "appearance": {
      "position": "bottom-right",
      "theme": "light",
      "icon": { "type": "preset", "preset": "chat", "url": null },
      "styles": {}
    },
    "urls": {
      "impression": "https://api.example.com/api/simple-surveys/onboarding-feedback/impression",
      "open": "https://api.example.com/api/simple-surveys/onboarding-feedback/open",
      "submit": "https://api.example.com/api/simple-surveys/onboarding-feedback/responses",
      "dismiss": "https://api.example.com/api/simple-surveys/onboarding-feedback/dismiss"
    },
    "questions": [
      {
        "key": "experience",
        "type": "rating",
        "label": "How would you rate your experience?",
        "help_text": null,
        "placeholder": null,
        "required": true,
        "options": {},
        "min": 1,
        "max": 5,
        "step": null,
        "max_length": null,
        "conditions": []
      },
      {
        "key": "low_score_reason",
        "type": "textarea",
        "label": "What could we have done better?",
        "help_text": null,
        "placeholder": null,
        "required": true,
        "options": {},
        "min": null,
        "max": null,
        "step": null,
        "max_length": 1000,
        "conditions": [
          { "question": "experience", "operator": "<=", "value": 3 }
        ]
      }
    ]
  }
}
```

### Duration Tracking (`duration_ms`)

- Pass `duration_ms` (milliseconds elapsed since the respondent opened the survey card) in `POST /{prefix}/{key}/responses`.
- If your client omits `duration_ms` but called `POST /{prefix}/{key}/open`, the server automatically computes `duration_ms` from `first_opened_at` to submission time.

---

## 4. Minimal React Widget Example

```jsx
import { useEffect, useRef, useState } from 'react';

export function SurveyWidget({ apiBase = 'https://api.example.com', token, pathname }) {
  const [survey, setSurvey] = useState(null);
  const [open, setOpen] = useState(false);
  const [answers, setAnswers] = useState({});
  const [errors, setErrors] = useState({});
  const [thanks, setThanks] = useState('');
  const openedAt = useRef(null);

  const headers = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    Authorization: `Bearer ${token}`,
  };

  useEffect(() => {
    if (!token) return;
    const url = `${apiBase}/api/simple-surveys/eligible?url=${encodeURIComponent(pathname)}`;

    fetch(url, { headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } })
      .then((r) => r.json())
      .then(({ survey: next }) => {
        setSurvey(next);
        setOpen(false);
        setAnswers({});
        setErrors({});
        setThanks('');
        openedAt.current = null;

        if (next && !sessionStorage.getItem(`lss_seen_${next.key}`)) {
          sessionStorage.setItem(`lss_seen_${next.key}`, '1');
          fetch(next.urls.impression, {
            method: 'POST',
            headers,
            body: JSON.stringify({ url: pathname }),
          }).catch(() => sessionStorage.removeItem(`lss_seen_${next.key}`));
        }
      });
  }, [apiBase, token, pathname]);

  if (!survey) return null;

  const handleOpen = () => {
    setOpen(true);
    if (!openedAt.current) {
      openedAt.current = Date.now();
      fetch(survey.urls.open, {
        method: 'POST',
        headers,
        body: JSON.stringify({ url: pathname }),
      }).catch(() => {});
    }
  };

  const handleDismiss = async (source) => {
    await fetch(survey.urls.dismiss, {
      method: 'POST',
      headers,
      body: JSON.stringify({ source, url: pathname }),
    });
    setSurvey(null);
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setErrors({});

    const res = await fetch(survey.urls.submit, {
      method: 'POST',
      headers,
      body: JSON.stringify({
        answers,
        url: pathname,
        duration_ms: openedAt.current ? Date.now() - openedAt.current : undefined,
      }),
    });

    const data = await res.json().catch(() => ({}));
    if (res.status === 422) {
      setErrors(data.errors || {});
      return;
    }
    if (res.ok) {
      setThanks(data.message || survey.thank_you_message);
      setTimeout(() => setSurvey(null), 3000);
    }
  };

  const setAnswer = (key, value) => setAnswers((prev) => ({ ...prev, [key]: value }));

  if (!open) {
    return (
      <div className="survey-banner">
        <span>{survey.banner_text}</span>
        <button type="button" onClick={handleOpen}>Take Survey</button>
        {survey.dismissible && (
          <button type="button" onClick={() => handleDismiss('banner')} aria-label="Dismiss">&times;</button>
        )}
      </div>
    );
  }

  return (
    <div className="survey-card" role="dialog" aria-label={survey.title}>
      <h3>{survey.title}</h3>
      {thanks ? (
        <p>{thanks}</p>
      ) : (
        <form onSubmit={handleSubmit}>
          {survey.questions.map((q) => (
            <div key={q.key}>
              <label>{q.label}{q.required ? ' *' : ''}</label>
              {q.type === 'rating' && (
                <div>
                  {Array.from({ length: (q.max ?? 5) - (q.min ?? 1) + 1 }, (_, i) => (q.min ?? 1) + i).map((n) => (
                    <button key={n} type="button" onClick={() => setAnswer(q.key, n)} aria-pressed={answers[q.key] === n}>
                      {n}
                    </button>
                  ))}
                </div>
              )}
              {q.type === 'select' && (
                <select value={answers[q.key] ?? ''} onChange={(e) => setAnswer(q.key, e.target.value)}>
                  <option value="">{q.placeholder || 'Select an option...'}</option>
                  {Object.entries(q.options).map(([val, label]) => (
                    <option key={val} value={val}>{label}</option>
                  ))}
                </select>
              )}
              {q.type === 'boolean' && (
                <div>
                  <button type="button" onClick={() => setAnswer(q.key, true)}>Yes</button>
                  <button type="button" onClick={() => setAnswer(q.key, false)}>No</button>
                </div>
              )}
              {(q.type === 'text' || q.type === 'number') && (
                <input
                  type={q.type}
                  value={answers[q.key] ?? ''}
                  placeholder={q.placeholder || ''}
                  onChange={(e) => setAnswer(q.key, e.target.value)}
                />
              )}
              {q.type === 'textarea' && (
                <textarea
                  value={answers[q.key] ?? ''}
                  placeholder={q.placeholder || ''}
                  onChange={(e) => setAnswer(q.key, e.target.value)}
                />
              )}
              {errors[`answers.${q.key}`] && <p role="alert">{errors[`answers.${q.key}`][0]}</p>}
            </div>
          ))}
          <div>
            {survey.dismissible && (
              <button type="button" onClick={() => handleDismiss('card')}>
                {survey.reprompt_after_dismiss ? 'Remind me later' : "Don't show again"}
              </button>
            )}
            <button type="submit">{survey.submit_label}</button>
          </div>
        </form>
      )}
    </div>
  );
}
```

> **Using Sanctum cookie-based SPA auth instead of Bearer tokens?** Omit the `Authorization` header, pass `credentials: 'include'` (plus `X-XSRF-TOKEN` on `POST` requests), and configure `SANCTUM_STATEFUL_DOMAINS`.

---

← Previous: [Appearance & Styling](styling.md) | Next: [Admin Results Dashboard →](admin-dashboard.md)
