# GitHub Copilot Instructions

## Commit Message Format for API Filter Additions

When adding new query parameters or filters to API endpoints, always generate commit messages using this structured format:

### Structure

**commit:**

```
[Brief title describing the change]
```

**new parameters:**

```
[parameter_name]=[example_value] - [Short description of what the parameter does]
```

**example:**

```
[API endpoint path]?[parameter_name]=[example_value]
```

**description:**

```
[Detailed explanation of the feature's purpose and benefits]
```

### Guidelines

1. **commit:** Should be concise and action-oriented (50 chars max)
2. **new parameters:** Must include parameter name, example value, and clear description
3. **example:** Provide a complete, copy-paste ready API call example
4. **description:** Explain the business value and use cases

### Example

**commit:**

```
Add topics filter to /v1.0/reviews API
```

**new parameters:**

```
topics=food quality - Filter reviews that contain the specified topic in their topics array
```

**example:**

```
/v1.0/reviews?topics=food quality
```

**description:**

```
This filter allows users to quickly find reviews related to specific topics like "food quality", "staff service", "cleanliness", or "ambiance", useful for targeted review analysis and management.
```

### Git Commit Command Format

When combining for git commit, use:

```bash
git commit -m "[Title]

new parameters: [parameter]=[value] - [description]

example: [API path]?[parameter]=[value]

description: [detailed explanation]"
```

### Additional Rules

-   Each section should be in a separate markdown code block for easy copying
-   Always provide practical, real-world examples
-   Escape special characters in git commit commands (e.g., quotes)
-   Keep descriptions focused on user benefits and use cases
-   Include multiple example values if the parameter accepts different types

---

## Commit Message Format for Bug Fixes and Issue Resolutions

When fixing bugs or resolving issues, always generate commit messages using this structured format:

### Structure

**commit:**

```
[Action verb: Fix/Resolve/Correct] [brief description of the issue]
```

**issue:**

```
[Description of what was wrong or the problem that occurred]
```

**solution:**

```
[Explanation of how the issue was fixed]
```

**changes:**

```
- [List of specific changes made]
- [Each change on a new line]
```

**impact:**

```
[What this fix improves or who benefits from it]
```

### Guidelines

1. **commit:** Start with action verb (Fix, Resolve, Correct, Address) followed by concise description
2. **issue:** Clearly describe the problem, error, or unexpected behavior
3. **solution:** Explain the approach taken to fix the issue
4. **changes:** List specific file changes or code modifications (optional but recommended for complex fixes)
5. **impact:** Describe the positive outcome or user benefit

### Example

**commit:**

```
Fix sentiment score filtering logic in ReviewNewController
```

**issue:**

```
Sentiment score filters were using cumulative conditions (>=) causing overlapping ranges and incorrect filtering results
```

**solution:**

```
Changed filter conditions to use proper non-overlapping ranges with upper and lower bounds for each sentiment category
```

**changes:**

```
- Updated very_positive filter: >= 0.8 (unchanged)
- Updated positive filter: >= 0.6 AND < 0.8
- Updated neutral filter: >= 0.4 AND < 0.6
- Updated negative filter: >= 0.2 AND < 0.4
- Added very_negative filter: < 0.2
```

**impact:**

```
Reviews are now correctly filtered by sentiment score without overlapping results, providing accurate sentiment-based review analysis
```

### Git Commit Command Format

When combining for git commit, use:

```bash
git commit -m "[Action verb] [brief description]

issue: [problem description]

solution: [how it was fixed]

changes:
- [change 1]
- [change 2]

impact: [benefit or improvement]"
```

### Quick Fix Format (for simple fixes)

For simple, obvious fixes, you can use a shorter format:

**commit:**

```
Fix [specific issue]
```

**description:**

```
[Brief explanation of the fix and its impact]
```

Example:

```bash
git commit -m "Fix controller import case sensitivity in api.php

description: Corrected BusinessAiModuleController import to match actual class name, resolving Swagger generation errors"
```
