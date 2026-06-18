# Forms Management

## Table of contents

1. [Creating forms](#creating-forms)
2. [Using the form builder](#using-the-form-builder)
3. [Question configuration](#question-configuration)
4. [Publishing and versioning](#publishing-and-versioning)
5. [Permissions and submissions](#permissions-and-submissions)
6. [Best practices](#best-practices)

---

## Creating forms

To create a new form:

1. open **Forms**
2. choose **Create Form**
3. enter a name, description, and instructions
4. save the form as a draft

Draft is the correct starting state for new forms. It allows safe editing before anything becomes visible to applicants.

---

## Using the form builder

The form builder allows administrators to:

- add questions
- reorder questions
- create sections
- define help text
- configure validation
- add conditional logic
- configure file uploads
- preview the form before publication

Supported question types include:

- text
- textarea
- email
- number
- date
- dropdown
- checkboxes
- radio buttons
- file upload
- section header

---

## Question configuration

Typical configuration options include:

- label and help text
- required vs optional
- placeholder text
- min/max length
- pattern validation
- option lists for selection questions
- file type and size rules for uploads
- conditional visibility rules

For evaluation scenarios, administrators can also configure scoring where supported.

---

## Publishing and versioning

### Publishing workflow

1. complete the draft
2. preview and validate the user experience
3. publish the form

### Versioning model

- draft forms are editable
- published forms are live for users
- a new version can be created from an existing published form
- existing submissions remain attached to the version used at the time of submission

---

## Permissions and submissions

Form-level administration can be shared with specific users using limited permissions such as:

- view
- review
- admin

Submission management typically includes:

- filtering by status
- viewing full responses
- requesting clarifications
- approving or rejecting where applicable
- exporting data

---

## Best practices

- use clear, descriptive form names
- group related questions into sections
- keep instructions concise but complete
- test logic and file uploads before publication
- use form-specific permissions where possible instead of broad role escalation
