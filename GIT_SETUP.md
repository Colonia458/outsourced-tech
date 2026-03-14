# How to Upload to GitHub

## Step 1: Create GitHub Repository
1. Go to [github.com](https://github.com)
2. Click **+** → **New repository**
3. Name it: `outsourced-tech`
4. Click **Create repository**
5. **Don't** initialize with README (we have files already)

## Step 2: Push Code from XAMPP

Open **Git Bash** (or CMD) in your project folder:

```bash
# 1. Initialize git (if not already)
git init

# 2. Add all files
git add .

# 3. First commit
git commit -m "Initial commit - E-commerce with automation"git innit

# 4. Add GitHub remote (replace YOUR_USERNAME)
git remote add origin https://github.com/YOUR_USERNAME/outsourced-tech.git

# 5. Push to GitHub
git push -u origin main
```

## Step 3: If Already Have Git Repo
```bash
git add .
git commit -m "Added automation features"
git push origin main
```

## Common Git Commands

| Command | Description |
|---------|-------------|
| `git status` | See changed files |
| `git add .` | Stage all changes |
| `git commit -m "message"` | Save changes |
| `git push` | Upload to GitHub |
| `git pull` | Download changes |

## After Upload to GitHub:
1. Go to **render.com**
2. Connect your GitHub account
3. Deploy from the repository

That's it!