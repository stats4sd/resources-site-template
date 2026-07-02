**Draft PR description for `trove-review-update` branch**


This PR completely reworks the trove draft / publishing process, and the 'review' workflow.

The goals of this work was to:

1. Simplify: Both the code and the front end was over complicated, making it hard for both devs and users to fully understand the status of any individual trove.
2. Harmonise: The app was reliant on the LaravelDrafts and FilamentDrafts packages to manage the draft/publishing flow, while only using a small amount of those package's features and actually overwriting a bunch of things. This PR removes those external dependencies and brings the logic together within this app.

## Draft --> Publishing Workflow

In this new version, for a 'logical trove', there are now _at most_ 2 versions:

- A canonical version, which is either an unpublished draft, or the current published version
- A "shadow draft" copy, which is a clone of the published version so users can make draft edits without affecting the published version.

Shadow drafts are linked to the canonical version via the `published_id` field. There is a unique() constraint on this field to ensure there is only ever 1 shadow draft copy of a published trove.

Every single state change relating to drafts and publishing is brought together into the `app\Services\TrovePublisher` class. All the logic for publishing, cloning new shadow drafts, and reviewing troves lives here.

## Review Process

> [!NOTE]
>  We had previously turned to use the term "checking" instead of review - but not in 100% of places. I decided to revert that - it is now "review" (I think this is more universal), and it's up to who-ever is managing the library to decide what that means to them.

`Trove` models now have a single ReviewStatus attribute, which pulls the logic determining the review state of an app into a single place. This uses an enum to ensure consistency. In theory, all places in the app that need to check the trove 'status' should pull from either:

- this ReviewStatus attribute;
- the simpler "isPublished()" attribute, for simple checks like showing items on the front-end.





### User Workflow

1. A new trove is created -> an unpublished draft.  (Trove ReviewStatus === Draft)
2. There are 3 options at the bottom of the edit page:
    - Save Draft (saves the changes and doesn't change the status)
    - Request Review -> opens a popup asking for the user to review (Trove ReviewStatus === InReview)
    - Publish -> opens a popup for you to confirm; asks for an explicit tickbox if the trove is not reviewed (Trove ReviewStatus === Published)

3. An InReview trove gains a new button in the header -> Mark as Reviewed. A user can click that to mark the Trove as Reviewed.
