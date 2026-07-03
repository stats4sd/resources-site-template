# The Trove Review Process — A Guide for Staff

This document explains how the "review" process for Troves works on the Resources platform. It is written for the people who actually create, edit, review and publish Troves — not for programmers. It describes how the process behaves today, so that everyone shares the same understanding of what happens when you click each button.

It is not a step-by-step tutorial for using the admin screens (though it does walk through the key screen). It is a description of *how the process works* and *why things behave the way they do*.

## What is being reviewed?

A **Trove** is a single learning resource — a document, a video, a link, a set of files, and so on. Troves are created and managed in the admin panel (the "back office" at `/admin`), and once published they appear on the public website for visitors to find and download.

The review process exists so that, before a Trove goes live for the public, a second person from the team can look it over and catch any problems — a wrong tag, a broken link, a typo, a missing file, an unclear description.

## The three states of a Trove

At any moment, a Trove is in one of three situations. It helps to think of these as three states:

1. **Draft** — a work in progress. It exists in the admin panel but is **not** visible to the public. You can keep editing it as long as you like.
2. **Awaiting a check (review requested)** — still a draft, still not public, but someone on the team has been formally asked to look at it.
3. **Published** — live on the public website. Anyone visiting the site can see it.

A Trove does not have to pass through all three states in order. You *can* publish something immediately without asking anyone to check it (the system will gently warn you when you do). The review step is a recommended safety net, not a hard gate.

## Versions and history (an important idea)

The platform keeps a **history of versions** for each Trove. This matters for understanding the whole process, so it is worth explaining up front.

- There is always (at most) **one published version** — the one the public sees.
- There is always **one "current" version** — the latest version you are working on in the admin panel. For a brand-new Trove this is a draft; for a live Trove that you start editing, a new working version is created so the public copy is not disturbed.
- Older versions are kept as **history** (a limited number of the most recent ones are retained), so you can look back at how a Trove changed over time.

The single most important consequence of this design:

> **Editing a published Trove does not immediately change what the public sees.** When you open a live Trove and make changes, those changes are held as a separate working version. The public keeps seeing the old, published version until you explicitly publish your changes.

This is deliberate. It means you can safely tidy up or correct a live resource, ask someone to check your changes, and only make them public when everyone is happy.

## Walking through the process

When you create or edit a Trove, you move through a series of steps (a "wizard"): **Details → Tags → Content → Cover Image → Check**. The first four steps are where you enter all the information about the resource. The last step, **Check**, is where you decide what should happen to the Trove next. This is the heart of the review process.

> When you are *editing* an existing Trove, you can jump straight to any step. When you are *creating* a brand-new Trove, you are guided through the steps in order.

### The "Check" step

The Check step opens with a short reminder: once a Trove is ready, it is a good idea to invite someone to check it over before it goes live. It then asks you a single question:

**"What do you want to do with this trove?"** — with three choices:

- **Save the trove as a draft**
- **Request a Review / Check**
- **Publish it!**

The screen changes depending on which one you pick.

#### Option 1 — Save as a draft

This simply saves your work without publishing it. The Trove stays private, and you can come back and finish it later. Use this when you are not done yet, or when you want to step away.

Choosing this and saving leaves the Trove sitting quietly in your drafts.

#### Option 2 — Request a Review / Check

This is the formal "please check my work" step. You choose **the person you want to ask** from a list of team members, and then save. Behind the scenes, two things are recorded:

- **Who you asked** (the checker).
- **That you were the one who asked** (the requester — i.e. you).

The Trove is saved as a draft (it stays private) and is now flagged as *awaiting a check*. Nothing is published.

There is no automatic email sent to the person you choose. Instead, the request becomes visible inside the admin panel: there is a dedicated **"Check Requested"** tab in the Trove list (see below) where Troves that are waiting to be checked are gathered together.

#### Option 3 — Publish it!

This makes the Trove live on the public website immediately.

Because publishing is significant, the screen adapts to the situation and may show one or two **warnings**:

- If **no-one has been asked to check** the Trove, you will see a warning pointing this out, and you must tick a box that says **"I am sure I want to publish this trove"** before the publish button becomes available. This is the system's way of nudging you to consider asking for a review first.
- If **you previously asked someone else to check** this Trove (you were the requester), you will see a second warning asking whether you really want to publish it *before* it has been checked. Again, you must confirm with the tick-box.

If a checker *has* been assigned and you are not overriding your own earlier request, the publish button is available without the extra tick-box — the assumption being that the proper process has been followed.

The publish button itself is labelled **"Save and Publish"** for a new Trove, or **"Save and Publish Changes"** when you are publishing edits to a Trove that already had a published version. That wording is a reminder that, for an existing live resource, you are replacing what the public currently sees with your new version.

## How a reviewer (checker) does a check

If you have been asked to check a Trove, here is how the process works from your side.

**Finding what needs checking.** In the Trove list in the admin panel there are several tabs across the top:

- **All** — every Trove's current working version.
- **Published** — only the versions that are live on the public site.
- **Drafts** — Troves that are currently unpublished works-in-progress.
- **Check Requested** — drafts that have had a checker assigned to them.

The **Check Requested** tab is the shared queue of Troves awaiting a check. (Note: this tab shows everything that has a checker assigned, not only the items assigned specifically to you — so the team can see the whole review queue at a glance.)

**Looking at the resource as the public would see it.** Each Trove in the list has a **"Preview on Front-end"** link. For a draft, this opens a private preview page showing the resource exactly as it will appear on the live site — but only you (a logged-in staff member) can see that preview; the public cannot. For an already-published Trove, the same link opens the live public page.

**Leaving feedback.** Each Trove in the list also has a **Comments** action. This opens a comment thread attached to that specific Trove, where you and the original uploader can discuss any changes that are needed. This is the built-in place for review conversation, so feedback stays attached to the resource rather than getting lost in email or chat.

**The outcome of a check.** Checking is a human process: the reviewer looks at the preview, leaves comments if something needs fixing, and the uploader makes corrections. When everyone is satisfied, someone publishes the Trove using the Check step described above. There is no separate "approve" button — approval is expressed by the act of publishing (or by the reviewer telling the uploader they are happy to publish).

## Editing a Trove that is already live

This is worth restating because it surprises people.

When you open a **published** Trove and make changes, the public site keeps showing the original version. Your changes are held as a separate working version until you publish them. This means:

- You can correct or improve a live resource without rushing.
- You can request a check on your *changes* in the same way as for a new Trove.
- The moment you choose **Publish** ("Save and Publish Changes"), your edited version replaces the public version, and the previous published content is kept in the version history.

There is also an **Unpublish** option available when editing, which takes a Trove off the public site (turning it back into a non-public item) without deleting it.

## A note on what the system does and does not do for you

To set expectations clearly:

- The system **does** keep your drafts private, keep a version history, let you preview drafts safely, record who asked whom for a check, gather review requests into a shared tab, and provide a comment thread for feedback.
- The system **does not** send automatic emails or notifications to a checker when you request a review, nor to the wider team when something is published. Some of the on-screen text mentions notifying the team; in practice, the way the team learns about review requests and new publications is through the admin panel itself (the Check Requested tab, the comments, and normal team communication) rather than through an automated message.

## Quick reference — what each choice does

| You choose… | What happens | Public can see it? |
|---|---|---|
| **Save as draft** | Work is saved privately; you can return to it later | No |
| **Request a Review / Check** | Saved as a draft; a checker and requester are recorded; it appears in the **Check Requested** tab | No |
| **Publish it!** (no checker) | After confirming a warning tick-box, the Trove goes live | Yes |
| **Publish it!** (checker assigned) | The Trove goes live | Yes |
| **Unpublish** (when editing a live Trove) | The Trove is removed from the public site but kept in the system | No |

## Glossary

- **Trove** — a single learning resource managed in the admin panel.
- **Draft** — an unpublished, private version of a Trove.
- **Published** — the live version visible to the public.
- **Current / working version** — the latest version you are editing in the admin panel.
- **Checker** — the person asked to review a Trove.
- **Requester** — the person who asked for the review.
- **Preview** — a private view of a draft, as the public site will render it, visible only to logged-in staff.
- **Version history** — the retained record of a Trove's previous versions.
