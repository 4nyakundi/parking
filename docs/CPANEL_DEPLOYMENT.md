# Publishing website changes

1. Commit and push the reviewed changes to GitHub `main`.
2. In cPanel Git Version Control, open **Parking Live Deployment**.
3. Choose **Manage → Pull or Deploy → Update from Remote**.
4. Confirm the expected commit appears, then choose **Deploy HEAD Commit**.
5. Wait for the deployed commit to appear, then check the affected live portal.

Use **Parking Live Deployment** for releases rather than the older **parking**
entry. Make feature changes in the development repository instead of editing
live files in File Manager.

Server configuration and stored records are preserved during deployment.
Database migrations are separate from publishing application files.
Firebase Hosting is also a separate release step when its driver page changes.
