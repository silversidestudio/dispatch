import { Button } from '@/components/ui/button';
import { Workspace } from '@/components/workspace';
import { router } from '@inertiajs/react';
export default function Accept({ token }: { token: string }) {
    return (
        <Workspace title="Join a workspace" subtitle="Accept using the verified account matching the invited email address.">
            <Button onClick={() => router.post(`/invitations/${token}`)}>Accept invitation</Button>
        </Workspace>
    );
}
