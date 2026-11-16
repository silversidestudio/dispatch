import { Button } from '@/components/ui/button';
import { Workspace } from '@/components/workspace';
import { router } from '@inertiajs/react';
export default function Accept({ token }: { token: string }) {
    return (
        <Workspace title="Join a workspace" subtitle="This invitation is linked to the verified email address it was sent to.">
            <Button onClick={() => router.post(`/invitations/${token}`)}>Accept invitation</Button>
        </Workspace>
    );
}
