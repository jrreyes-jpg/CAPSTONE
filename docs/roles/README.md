# Role Responsibilities

This folder defines the responsibility boundaries for each role in the system.

## Role Summary

| Role | Main Responsibility |
| --- | --- |
| Super Admin | Create and manage projects, assign users, and maintain the master inventory |
| Engineer | Monitor assigned projects, update tasks, and report project progress |
| Foreman | Track actual on-site asset usage, scan QR codes, and log field activity |
| Client | View project status only |

## Structure

- `super-admin.md`: create and manage projects, assign users, and control the master inventory
- `engineer.md`: ownership of project execution tracking and task progress
- `foreman.md`: ownership of site-level asset tracking and usage logs
- `client.md`: limited to viewing project status only

## Shared Rule

To reduce redundancy and avoid role overlap:

- `Super Admin` owns project setup, user assignment, and master inventory
- `Engineer` owns execution updates
- `Foreman` owns on-site asset movement and usage logs
- `Client` views project status only

## Why This Exists

These docs make role scope clear without duplicating logic across dashboard files.
