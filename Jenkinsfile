def getBranches() {
  return [
    "develop": [
      "S3_BUCKET": "prismapp-develop",
      "S3_PREFIX": "files/plugin-woocommerce",
    ],
    "releases": [
      "S3_BUCKET": "prismapp-staging",
      "S3_PREFIX": "files/plugin-woocommerce",
    ],
    "master": [
      "S3_BUCKET": "prismapp-files",
      "S3_PREFIX": "plugin-woocommerce",
    ],
  ]
}

def getVersion(String branch) {
  return branch.toLowerCase()
          .replaceAll('%2f', '')
          .replaceAll('[-_/]', '')
}

def getConfig(String branch) {
  def BRANCHES = getBranches()

  if (branch in BRANCHES) {
    return BRANCHES[branch]
  } else {
    return [
      "S3_BUCKET": BRANCHES.develop.S3_BUCKET,
      "S3_PREFIX": BRANCHES.develop.S3_PREFIX + "-" + getVersion(branch),
    ]
  }
}

milestone 100
stage('test') {
  // TODO implement this
}

milestone 200
stage('package') {
  node('docker') {
    checkout scm

    sh "git submodule init"
    sh "git submodule update --recursive"
    sh "git checkout HEAD -- woocommerce-prismappio.php"

    docker.image('composer/composer:1.1-alpine').inside() {
      sh "./wp-deps.sh"
    }

    version = getVersion(env.BRANCH_NAME)
    docker.image('alpine:3.4').inside() {
      sh "apk add --update bash rsync tar zip unzip sed"
      sh "./wp-version.sh ${version}"
      sh "./wp-package.sh"
    }

    stash includes: 'build/*.tar.gz,build/*.zip', name: 'artifacts'
  }
}

milestone 300
stage('publish') {
  node('docker') {
    unstash 'artifacts'
    archiveArtifacts 'build/*.tar.gz'
    archiveArtifacts 'build/*.zip'

    // UPLOAD artifacts to S3 bucket
    // TODO un-hardcode "aws-sabrina" maybe?
    withCredentials([usernamePassword(credentialsId: 'aws-sabrina',
            usernameVariable: 'AWS_ACCESS_KEY',
            passwordVariable: 'AWS_SECRET_KEY')]) {

        config = getConfig(env.BRANCH_NAME)
        s3cmd = tool('s3cmd') + "/s3cmd" // this tool runs on docker, btw
        sh """/bin/sh -e
        export AWS_ACCESS_KEY=${env.AWS_ACCESS_KEY}
        export AWS_SECRET_KEY=${env.AWS_SECRET_KEY}
        ${s3cmd} sync build/*.tar.gz s3://${config.S3_BUCKET}/${config.S3_PREFIX}/
        ${s3cmd} sync build/*.zip s3://${config.S3_BUCKET}/${config.S3_PREFIX}/
        """
    }
  }
}
