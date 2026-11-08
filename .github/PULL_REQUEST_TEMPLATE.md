## Summary of Changes

Briefly explain the goal of this pull request, the bug fixed, or the feature implemented.

Closes # (issue number if applicable)

---

## Type of Change

- [ ] 🐛 Bug fix (non-breaking change which fixes an issue)
- [ ] ✨ New feature (non-breaking change which adds functionality)
- [ ] 📝 Documentation update
- [ ] 🎨 Code style / formatting (Pint)
- [ ] ⚡ Performance improvement
- [ ] 🧪 Tests (added or updated tests)
- [ ] 🔧 Refactoring / Maintenance

---

## Detailed Changes

- Change 1...
- Change 2...

---

## Verification & Testing Performed

Describe the testing performed to ensure these changes work and do not introduce regressions:

### Local Automated Test Run:
```bash
# Paste command output or result summary:
php artisan test --compact
```

### Static Analysis & Code Style:
```bash
composer lint:check   # [ ] Passed
composer types:check  # [ ] Passed (0 errors)
```

### Manual Testing:
- [ ] Tested on local browser (Desktop / Mobile responsive)
- [ ] Verified with Demo user roles (Admin / Receptionist / Patient)
- [ ] Attached screenshots or screen recordings (if modifying UI)

---

## Screenshots / Screen Recordings (if applicable)

| Before | After |
| :--- | :--- |
| *(Image/GIF)* | *(Image/GIF)* |

---

## Contributor Checklist

- [ ] My code follows the project's coding style guidelines (`composer lint:check`).
- [ ] I have performed a self-review of my code.
- [ ] I have commented my code where necessary, particularly in complex scheduling / concurrency logic.
- [ ] I have added tests that prove my fix is effective or that my feature works.
- [ ] All existing and new tests pass locally (`php artisan test`).
- [ ] PHPStan passes with 0 errors (`composer types:check`).
- [ ] My changes generate no new warnings or deprecation notices.
- [ ] I have updated the documentation where appropriate (README, docs, etc.).

