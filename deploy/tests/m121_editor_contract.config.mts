// Read existing editor source; all runner/cache/temp artifacts stay in this integration worktree.
const editor='/Users/mac/Documents/dev/chinaplanet-OS/.worktrees/m121-editor-contract-integration';
const integration='/Users/mac/Documents/dev/chinaplanet-OS/.worktrees/m121-finance-office-integration';
export default {root:editor,cacheDir:integration+'/outputs/M121/local-assembly-2026-11-03/vite-cache',resolve:{alias:{'@':editor+'/client/src','@shared':editor+'/shared','@assets':editor+'/attached_assets'}},test:{environment:'node',cache:false,include:[integration+'/deploy/tests/test_m121_assembled_journey.test.ts',editor+'/server/**/*.test.ts',editor+'/client/src/**/*.test.ts']}};
